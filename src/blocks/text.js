import { useEffect, useState } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls, BlockControls } from '@wordpress/block-editor';
import { TextControl, CustomSelectControl, PanelBody, ToolbarGroup, ToolbarButton, Button } from '@wordpress/components';
import { edit } from '@wordpress/icons';
import { dispatch, select, useSelect } from '@wordpress/data'; // Use `useSelect` for fetching data
import { createBlock } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';

function createLowercaseUnderscored(string) {
       // Map of numbers to words
       const numberWords = {
        '0': 'zero',
        '1': 'one',
        '2': 'two',
        '3': 'three',
        '4': 'four',
        '5': 'five',
        '6': 'six',
        '7': 'seven',
        '8': 'eight',
        '9': 'nine'
    };

    // Replace each numeric character with its corresponding word
    const replacedString = string.replace(/\d/g, (match) => numberWords[match]);

    // Process the replaced string
    return replacedString
        .toLowerCase() // Convert to lowercase
        .replace(/[^a-z0-9_ ]+/g, '') // Remove any character that is not a-z, 0-9, or space
        .replace(/\s+/g, '_'); // Replace spaces with underscores
}


const updateParagraphPlaceholders = () => {
    const blocks = select('core/block-editor').getBlocks();

    if (blocks.length === 0) {
        setTimeout(updateParagraphPlaceholders, 10); // Retry if no blocks are found
        return;
    }

    blocks.forEach(block => {
        if (block.name === 'core/paragraph' && block.attributes.metadata?.bindings?.content) {
            const metaBinding = block.attributes.metadata.bindings.content.args;
            const metaKey = metaBinding ? metaBinding.key : undefined;

            if (!metaKey) {
                setTimeout(updateParagraphPlaceholders, 100); // Retry if meta key is missing
                return;
            }

            // Fetch the description for the specific meta key directly
            apiFetch({ path: `/ace/v1/get-choices?choice_name=${metaKey}` })
                .then((response) => {
                    // Find the correct choice based on the metaKey
                    const choice = response.choices.find(c => c.name === metaKey); // Use the correct choice
                    const updatedPlaceholder = choice?.description || "Add content here...";

                    // Reset lockState for the current block
                    let lockState = false;

                    // Check for permissions based on roles
                    if (choice && choice.roles.length > 0) {
                        const hasPermission = checkPermissions(choice.roles);
                        console.log('Has permission:', hasPermission);
                        // Apply locking based on permissions if there are roles
                        lockState = {
                            remove: !hasPermission,
                            move: !hasPermission,
                            edit: !hasPermission
                        };
                    } else {
                        // No roles means the block should be unlocked
                        lockState = false;
                    }

                    // Log current block and its final lock state for debugging
                    console.log(`Updating block ${block.clientId} - Lock State:`, lockState);

                    // Update block attributes with the correct placeholder and lock state
                    dispatch('core/block-editor').updateBlockAttributes(block.clientId, {
                        placeholder: updatedPlaceholder,
                        lock: lockState // Apply calculated lock state
                    });

                    // Trigger the save process to persist changes
                    dispatch('core/editor').editPost({ blocks: select('core/block-editor').getBlocks() });
                })
                .catch((error) => {
                    console.error("Failed to update placeholder:", error);
                });
        }
    });
};



// Function to check if the current user has permissions
const checkPermissions = (allowedRoles) => {
    const currentUserRoles = select('core').getCurrentUser()?.roles || [];
    return allowedRoles.some(role => currentUserRoles.includes(role));
};


domReady(() => {
    updateParagraphPlaceholders();
});

registerBlockType('adaptive-content-enhancer/text-field', {
    title: 'Meta Text',
    category: 'text',
    icon: 'editor-paragraph',
    attributes: {
        selectedMetaKey: { type: 'string', default: '' },
        content: { type: 'string', default: '' }
    },
    supports: {
        advanced: false,
        customClassName: false,
        html: false
    },
    edit: ({ attributes, setAttributes, clientId }) => {
        const { selectedMetaKey, content } = attributes;
        const [metaValue, setMetaValue] = useEntityProp('postType', 'post', 'meta');
        const [availableMetaKeys, setAvailableMetaKeys] = useState([]);
        const [showCreateFieldForm, setShowCreateFieldForm] = useState(false);
        const [newFieldName, setNewFieldName] = useState('');
        const [newFieldDescription, setNewFieldDescription] = useState('');
        const [currentPostType, setCurrentPostType] = useState(''); // State for current post type

        const blockProps = useBlockProps();

        useEffect(() => {
            if (window.choiceGroups) {
                console.log(choiceGroups);
                const textKeys = window.choiceGroups
                    .filter(group => group.field_type === 'text')
                    .map(group => ({ key: group.name, label: group.title }));
                setAvailableMetaKeys(textKeys);
            }
        }, []);

        const handleMetaKeyChange = ({ selectedItem }) => {
            const newMetaKey = selectedItem.key;
            console.log('Selected meta key:', selectedItem);
            if (newMetaKey === 'create-new') {
                setShowCreateFieldForm(true);
                return;
            }
            setAttributes({ selectedMetaKey: newMetaKey, content: '' });
            if (metaValue && metaValue[newMetaKey]) {
                setAttributes({ content: metaValue[newMetaKey] });
            }
            insertParagraphBlock(newMetaKey);
        };

        const insertParagraphBlock = (metaKey) => {
            console.log('Inserting paragraph block with meta key:', metaKey);
            // Fetch the placeholder description dynamically via the REST API
            apiFetch({ path: '/ace/v1/get-choices?field_type=text' }).then((response) => {
                console.log('Fetched choices:', response);
                const selectedChoice = response.choices.find(choice => choice.name === metaKey);
                const placeholderText = selectedChoice?.description || "Add content here...";

                const paragraphBlock = createBlock('core/paragraph', {
                    placeholder: placeholderText,
                    // Use a binding to ensure content is kept in sync with the meta key
                    metadata: {
                        bindings: {
                            content: {
                                source: "core/post-meta",
                                args: { key: metaKey }
                            }
                        }
                    }
                });
                        
                dispatch('core/block-editor').insertBlocks(paragraphBlock);
                dispatch('core/block-editor').removeBlock(clientId);
                dispatch('core/block-editor').selectBlock(paragraphBlock.clientId);

            }).catch((error) => {
                console.error("Failed to fetch placeholder:", error);
            });
        };

        const currentUserRoles = useSelect((select) => {
            const user = select('core').getCurrentUser();
            return user ? user.roles : []; // Return roles or an empty array if user is not available
        }, []);
    
// Get the current post type
useEffect(() => {
    const postType = select('core/editor').getCurrentPostType();
    setCurrentPostType(postType);
    console.log('Current post type:', postType);
    console.log('CG:', window);

    if (window.choiceGroups) {
        console.log('Current post type:', postType);

        const textKeys = window.choiceGroups
            .filter(group => {
                // Determine if global permissions apply
                const hasGlobalPermissions = !Array.isArray(group.roles) && !Array.isArray(group.post_types);
                console.log('Group:', group);

                // Check for post type permissions
                const hasPostTypePermission = hasGlobalPermissions || 
                    (Array.isArray(group.post_types) && (group.post_types.length === 0 || group.post_types.includes(postType)));

                console.log('Has post type permission:', hasPostTypePermission);

                // Check for role permissions
                const hasRolePermission = hasGlobalPermissions || 
                    (Array.isArray(group.roles) && (group.roles.length === 0 || group.roles.some(role => currentUserRoles.includes(role))));

                console.log('Has role permission:', group.roles);

                // Return true if it meets criteria for a text field and has the right permissions
                return group.field_type === 'text' && hasPostTypePermission && hasRolePermission;
            })
            .map(group => ({ key: group.name, label: group.title }));

        setAvailableMetaKeys(textKeys);
    }
}, [currentUserRoles, currentPostType]); // Include currentPostType in the dependency array




        const handleCreateField = () => {
            const newFieldData = {
                name: createLowercaseUnderscored(newFieldName),
                title: newFieldName,
                description: newFieldDescription,
                field_type: 'text',
            };
        
            apiFetch({
                path: `/ace/v1/save-options`,
                method: 'POST',
                data: { choices: [newFieldData] }
            }).then(() => {
                const updatedMetaKeys = [...availableMetaKeys, { key: newFieldData.name, label: newFieldData.title }];
        
                // Log current post meta attributes
                const currentMeta = select('core/editor').getEditedPostAttribute('meta');
        
                // Inject the new field into the post meta attributes
                const updatedMeta = {
                    ...currentMeta,
                    [newFieldData.name]: ''  // Set initial value for new meta field
                };
        
                // Use setTimeout to ensure WordPress registers the meta update
                setTimeout(() => {
                    dispatch('core/editor').editPost({ meta: updatedMeta });
                    const newMeta = select('core/editor').getEditedPostAttribute('meta');
                }, 10);
        
                // Update available meta keys and set the new field as the selected meta key
                setAvailableMetaKeys(updatedMetaKeys);
                setAttributes({ selectedMetaKey: newFieldData.name, content: '' });
                setShowCreateFieldForm(false);
                setNewFieldName('');
                setNewFieldDescription('');
        
                insertParagraphBlock(newFieldData.name);
            }).catch((error) => {
                console.error("Failed to create new field:", error);
            });
        };
        
        
        

        const toolbarControls = (
            <ToolbarGroup>
                <ToolbarButton
                    icon={edit}
                    label="Select Meta Field"
                    onClick={() => {}}
                    isPressed={false}
                />
            </ToolbarGroup>
        );

        return (
            <>
                <BlockControls>
                    {toolbarControls}
                </BlockControls>
                <div {...blockProps}>
                    {!selectedMetaKey && !showCreateFieldForm && (
                        <CustomSelectControl
                            label="Select or Create a Meta Field"
                            value={availableMetaKeys.find(key => key.key === selectedMetaKey) ? {
                                key: selectedMetaKey,
                                name: availableMetaKeys.find(key => key.key === selectedMetaKey)?.label
                            } : { key: '', name: 'Please select a meta field' }}
                            options={[
                                ...availableMetaKeys.map(key => ({
                                    name: key.label,
                                    key: key.key
                                })),
                                { name: 'Create New Field', key: 'create-new' }
                            ]}
                            onChange={handleMetaKeyChange}
                        />
                    )}
                    {showCreateFieldForm && (
                        <div>
                            <TextControl
                                label="New Field Name"
                                value={newFieldName}
                                onChange={(value) => setNewFieldName(value)}
                            />
                            <TextControl
                                label="Description"
                                value={newFieldDescription}
                                onChange={(value) => setNewFieldDescription(value)}
                            />
                            <Button isPrimary onClick={handleCreateField}>
                                Save New Field
                            </Button>
                            <Button onClick={() => setShowCreateFieldForm(false)}>
                                Cancel
                            </Button>
                        </div>
                    )}
                    {selectedMetaKey && (
                        <TextControl
                            label="Custom Text"
                            value={content}
                            onChange={(newContent) => setAttributes({ content: newContent })}
                            placeholder="Enter text bound to the selected meta field..."
                        />
                    )}
                </div>
                <InspectorControls>
                    <PanelBody title="Settings">
                       
                    <CustomSelectControl
    label="Select or Create a Meta Field"
    value={availableMetaKeys.find(key => key.key === selectedMetaKey) ? {
        key: selectedMetaKey,
        name: availableMetaKeys.find(key => key.key === selectedMetaKey)?.label
    } : { key: '', name: 'Please select a meta field' }}
    options={[
        ...availableMetaKeys.map(key => ({
            name: key.label,
            key: key.key
        })),
        { name: 'Create New Field', key: 'create-new' }
    ]}
    onChange={handleMetaKeyChange}
/>


                    </PanelBody>
                </InspectorControls>
            </>
        );
    },
    save: () => {
        return null;
    }
});
