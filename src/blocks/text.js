import { useEffect, useState } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { registerBlockType, createBlock, registerBlockVariation } from '@wordpress/blocks';
import { useBlockProps, InspectorControls, BlockControls } from '@wordpress/block-editor';
import {
	TextControl,
	CustomSelectControl,
	PanelBody,
	ToolbarGroup,
	ToolbarButton,
	Button,
	TextareaControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { edit } from '@wordpress/icons';
import { dispatch, select, useSelect } from '@wordpress/data'; // Use `useSelect` for fetching data
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
        content: { type: 'string', default: '' },
        isInsideForm: { type: 'boolean', default: false },
        title: { type: 'string', default: '' },
        description: { type: 'string', default: '' },
        label: { type: 'string', default: '' },
        placeholder: { type: 'string', default: '' },
        helpText: { type: 'string', default: '' },
        inputType: { type: 'string', default: 'text' },
        autocomplete: { type: 'string', default: '' },
        isRequired: { type: 'boolean', default: false },
        rows: { type: 'number', default: 4 },
        minLength: { type: 'number', default: 0 },
        maxLength: { type: 'number', default: 0 }
    },
    supports: {
        html: false,
        styles: true,
        customClassName: true,
        attributes: true,
        color: {
            text: true,
            background: true,
            gradients: true, // Enable gradient options
            link: true, // Enable link color
        },
        typography: {
            fontSize: true,
            lineHeight: true,
            textAlign: true,
            __experimentalFontWeights: true,
            __experimentalFontFamily: true,
            __experimentalFontStyle: true,
            __experimentalFontVariant: true,
            __experimentalLetterSpacing: true,
            __experimentalTextTransform: true,
            customLineHeight: true,
            __experimentalCustomFontSize: true,
        },
        spacing: {
            padding: true,
            margin: true,
        },
        __experimentalBorder: {
            color: true,
            radius: true,
            style: true,
            width: true,
            __experimentalDefaultControls: {
                color: true,
                radius: true,
                style: true,
                width: true,
            },
        },
        align: true, // Enable alignment options (left, center, right, wide, full)
    },
    edit: ({ attributes, setAttributes, clientId }) => {
        const { selectedMetaKey, content } = attributes;
        const [metaValue, setMetaValue] = useEntityProp('postType', 'post', 'meta');
        const [availableMetaKeys, setAvailableMetaKeys] = useState([]);
        const [showCreateFieldForm, setShowCreateFieldForm] = useState(false);
        const [newFieldName, setNewFieldName] = useState('');
        const [newFieldDescription, setNewFieldDescription] = useState('');
        const [fieldTitle, setFieldTitle] = useState('');
        const [fieldDescription, setFieldDescription] = useState('');
        const [currentPostType, setCurrentPostType] = useState(''); // State for current post type


        const isInsideForm = useSelect((select) => {
            const { getBlockRootClientId, getBlock } = select('core/block-editor');
            const parentClientId = getBlockRootClientId(clientId);
            const parentBlock = getBlock(parentClientId);
            return parentBlock?.name === 'adaptive-content-enhancer/form';
        }, [clientId]);



        const blockProps = useBlockProps({
            className: 'ace-text-field wp-block',
        });

        console.log('Is inside form:', isInsideForm);

        useEffect(() => {
            setAttributes({ isInsideForm });
        }, [isInsideForm]);
        
        


        useEffect(() => {
            if (window.choiceGroups) {
                console.log(choiceGroups);
                const textKeys = window.choiceGroups
                    .filter(group => group.field_type === 'text')
                    .map(group => ({
                        key: group.name,
                        label: group.title,
                        title: group.title,
                        description: group.description || '',
                        field_type: group.field_type,
                        show_in_rest: group.show_in_rest,
                        options: group.options || [],
                        tags: group.tags || [],
                        associated_roles: group.associated_roles || [],
                        post_types: group.post_types || [],
                    }));
                setAvailableMetaKeys(textKeys);
            }
        }, []);

        useEffect(() => {
            if (!selectedMetaKey) {
                setFieldTitle('');
                setFieldDescription('');
                return;
            }

            const selectedField = availableMetaKeys.find((field) => field.key === selectedMetaKey);
            if (!selectedField) {
                return;
            }

            setFieldTitle(selectedField.title || selectedField.label || '');
            setFieldDescription(selectedField.description || '');
        }, [selectedMetaKey, availableMetaKeys]);

        const syncFieldDefinition = (fieldKey, updates) => {
            setAvailableMetaKeys((previousFields) =>
                previousFields.map((field) =>
                    field.key === fieldKey
                        ? {
                                ...field,
                                ...updates,
                                label: updates.title || field.label,
                                title: updates.title || field.title,
                            }
                        : field
                )
            );

            if (Array.isArray(window.choiceGroups)) {
                window.choiceGroups = window.choiceGroups.map((field) =>
                    field.name === fieldKey ? { ...field, ...updates } : field
                );
            }
        };

        const saveFieldDefinition = (fieldKey, definitionOverrides = {}) => {
            const existingField = availableMetaKeys.find((field) => field.key === fieldKey);
            if (!existingField) {
                return Promise.resolve();
            }

            const updatedField = {
                name: fieldKey,
                title: definitionOverrides.title || existingField.title || existingField.label || fieldKey,
                description:
                    definitionOverrides.description ?? existingField.description ?? '',
                field_type: existingField.field_type || 'text',
                show_in_rest: existingField.show_in_rest ? 1 : 0,
                options: existingField.options || [],
                tags: existingField.tags || [],
                associated_roles: existingField.associated_roles || [],
                post_types: existingField.post_types || [],
            };

            return apiFetch({
                path: '/ace/v1/save-options',
                method: 'POST',
                data: { choices: [ updatedField ] },
            }).then(() => {
                syncFieldDefinition(fieldKey, updatedField);
                setAttributes({
                    title: updatedField.title,
                });
            }).catch((error) => {
                console.error('Failed to save field definition:', error);
            });
        };

        const handleMetaKeyChange = ({ selectedItem }) => {
            const newMetaKey = selectedItem.key;
        
            console.log('Selected meta key:', selectedItem);
        
            if (newMetaKey === 'create-new') {
                setShowCreateFieldForm(true);
                return;
            }

            const selectedField =
                availableMetaKeys.find((field) => field.key === newMetaKey) || selectedItem;
         
            const newTitle = selectedItem.name || ''; // Use the name of the selected item as the title
         
            // Update selected meta key, title, and content
            setAttributes({ 
                selectedMetaKey: newMetaKey, 
                title: selectedField.title || newTitle,
                description: selectedField.description || '',
                content: '' 
            });
        
            if (metaValue && metaValue[newMetaKey]) {
                setAttributes({ content: metaValue[newMetaKey] });
            }
        
            if (isInsideForm) {
                console.log('Block is inside a form container, skipping paragraph insertion.');
                return;
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
            return user && Array.isArray(user.roles) ? user.roles : [];
        }, []);
    
// Get the current post type
useEffect(() => {
    const postType = select('core/editor').getCurrentPostType();
    setCurrentPostType(postType);

    if (window.choiceGroups) {

        const textKeys = window.choiceGroups
            .filter(group => {
                // Determine if global permissions apply
                const hasGlobalPermissions = !Array.isArray(group.roles) && !Array.isArray(group.post_types);
                console.log('Group:', group);

                // Check for post type permissions
                const hasPostTypePermission = hasGlobalPermissions || 
                    (Array.isArray(group.post_types) && (group.post_types.length === 0 || group.post_types.includes(postType)));


                // Check for role permissions
                const hasRolePermission = hasGlobalPermissions || 
    (Array.isArray(group.roles) && currentUserRoles && (group.roles.length === 0 || group.roles.some(role => currentUserRoles.includes(role))));



                // Return true if it meets criteria for a text field and has the right permissions
                return group.field_type === 'text' && hasPostTypePermission && hasRolePermission;
            })
            .map(group => ({
                key: group.name,
                label: group.title,
                title: group.title,
                description: group.description || '',
                field_type: group.field_type,
                show_in_rest: group.show_in_rest,
                options: group.options || [],
                tags: group.tags || [],
                associated_roles: group.associated_roles || [],
                post_types: group.post_types || [],
            }));

        setAvailableMetaKeys(textKeys);
    }
}, [currentUserRoles, currentPostType]); // Include currentPostType in the dependency array




        const handleCreateField = () => {
            const newFieldData = {
                name: createLowercaseUnderscored(newFieldName),
                title: newFieldName,
                description: newFieldDescription,
                field_type: 'text',
                show_in_rest: 1,
            };
        
            apiFetch({
                path: `/ace/v1/save-options`,
                method: 'POST',
                data: { choices: [newFieldData] }
            }).then(() => {
                const createdField = {
                    key: newFieldData.name,
                    label: newFieldData.title,
                    title: newFieldData.title,
                    description: newFieldData.description,
                    field_type: newFieldData.field_type,
                    show_in_rest: newFieldData.show_in_rest,
                    options: [],
                    tags: [],
                    associated_roles: [],
                    post_types: [],
                };
                const updatedMetaKeys = [...availableMetaKeys, createdField];

                if (Array.isArray(window.choiceGroups)) {
                    window.choiceGroups = [...window.choiceGroups, newFieldData];
                }
         
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
                setAttributes({
                    selectedMetaKey: newFieldData.name,
                    title: newFieldData.title,
                    description: newFieldData.description,
                    content: '',
                });
                setShowCreateFieldForm(false);
                setNewFieldName('');
                setNewFieldDescription('');

                if (!isInsideForm) {
                    insertParagraphBlock(newFieldData.name);
                }
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
                        attributes.inputType === 'textarea' ? (
                            <TextareaControl
                                label={attributes.label || `${attributes.selectedMetaKey}`}
                                value={attributes.content}
                                onChange={(newContent) => setAttributes({ content: newContent })}
                                placeholder={attributes.placeholder || 'Enter text bound to the selected meta field...'}
                                rows={attributes.rows || 4}
                            />
                        ) : (
                            <TextControl
                                label={attributes.label || `${attributes.selectedMetaKey}`}
                                type={attributes.inputType || 'text'}
                                value={attributes.content}
                                onChange={(newContent) => setAttributes({ content: newContent })}
                                placeholder={attributes.placeholder || 'Enter text bound to the selected meta field...'}
                            />
                        )
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
                    {selectedMetaKey && (
                        <>
                            <TextControl
                                label="Field Title"
                                value={fieldTitle}
                                onChange={(value) => {
                                    setFieldTitle(value);
                                    setAttributes({ title: value });
                                }}
                            />
                            <TextareaControl
                                label="Field Description / Label Copy"
                                value={fieldDescription}
                                onChange={(value) => {
                                    setFieldDescription(value);
                                    setAttributes({ description: value });
                                }}
                            />
                            <Button
                                isPrimary
                                onClick={() =>
                                    saveFieldDefinition(selectedMetaKey, {
                                        title: fieldTitle,
                                        description: fieldDescription,
                                    })
                                }
                            >
                                Save Field Definition
                            </Button>
                        </>
                    )}
                    </PanelBody>
                    {selectedMetaKey && (
                        <PanelBody title="Form Field Settings" initialOpen={false}>
                            <SelectControl
                                label="Input Type"
                                value={attributes.inputType || 'text'}
                                options={[
                                    { label: 'Text', value: 'text' },
                                    { label: 'Email', value: 'email' },
                                    { label: 'Phone', value: 'tel' },
                                    { label: 'URL', value: 'url' },
                                    { label: 'Number', value: 'number' },
                                    { label: 'Textarea', value: 'textarea' },
                                ]}
                                onChange={(value) => setAttributes({ inputType: value })}
                            />
                            <TextControl
                                label="Custom Label"
                                help="Overrides the shared field title for this block only."
                                value={attributes.label || ''}
                                onChange={(value) => setAttributes({ label: value })}
                            />
                            <TextControl
                                label="Placeholder"
                                value={attributes.placeholder || ''}
                                onChange={(value) => setAttributes({ placeholder: value })}
                            />
                            <TextareaControl
                                label="Help Text"
                                value={attributes.helpText || ''}
                                onChange={(value) => setAttributes({ helpText: value })}
                            />
                            <TextControl
                                label="Autocomplete"
                                help="Examples: name, email, tel, organization, street-address."
                                value={attributes.autocomplete || ''}
                                onChange={(value) => setAttributes({ autocomplete: value })}
                            />
                            <ToggleControl
                                label="Required field"
                                checked={!!attributes.isRequired}
                                onChange={(value) => setAttributes({ isRequired: value })}
                            />
                            {attributes.inputType === 'textarea' && (
                                <TextControl
                                    label="Textarea Rows"
                                    type="number"
                                    value={attributes.rows || 4}
                                    onChange={(value) => setAttributes({ rows: parseInt(value, 10) || 4 })}
                                />
                            )}
                            <TextControl
                                label="Minimum Length"
                                type="number"
                                value={attributes.minLength || ''}
                                onChange={(value) => setAttributes({ minLength: parseInt(value, 10) || 0 })}
                            />
                            <TextControl
                                label="Maximum Length"
                                type="number"
                                value={attributes.maxLength || ''}
                                onChange={(value) => setAttributes({ maxLength: parseInt(value, 10) || 0 })}
                            />
                        </PanelBody>
                    )}
                </InspectorControls>
            </>
        );
    },
    save: () => {
        return null;
    }
});

[
    {
        name: 'email-field',
        title: 'Email Field',
        description: 'A text field variation configured for email addresses.',
        icon: 'email',
        attributes: { inputType: 'email' },
    },
    {
        name: 'phone-field',
        title: 'Phone Field',
        description: 'A text field variation configured for phone numbers.',
        icon: 'phone',
        attributes: { inputType: 'tel' },
    },
    {
        name: 'url-field',
        title: 'URL Field',
        description: 'A text field variation configured for web addresses.',
        icon: 'admin-links',
        attributes: { inputType: 'url' },
    },
    {
        name: 'textarea-field',
        title: 'Textarea Field',
        description: 'A larger multi-line text input for messages and notes.',
        icon: 'editor-alignleft',
        attributes: { inputType: 'textarea', rows: 5 },
    },
].forEach( ( variation ) => {
    registerBlockVariation( 'adaptive-content-enhancer/text-field', {
        ...variation,
        scope: [ 'inserter', 'block' ],
        isActive: ( blockAttributes ) =>
            blockAttributes.inputType === variation.attributes.inputType,
    } );
} );
