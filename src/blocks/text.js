import { useEffect, useState } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls, BlockControls } from '@wordpress/block-editor';
import { TextControl, CustomSelectControl, PanelBody, ToolbarGroup, ToolbarButton, Button } from '@wordpress/components';
import { edit } from '@wordpress/icons';
import { dispatch, select } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';

function createLowercaseUnderscored(string) {
    return string.toLowerCase().replace(/\s+/g, '_');
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
                    const choice = response.choices[0];
                    const updatedPlaceholder = choice?.description || "Add content here...";

                    // Update the paragraph block's placeholder if it's different
                    if (block.attributes.placeholder !== updatedPlaceholder) {
                        dispatch('core/block-editor').updateBlockAttributes(block.clientId, {
                            placeholder: updatedPlaceholder
                        });

                        // Trigger the save process to persist changes
                        dispatch('core/editor').editPost({ blocks: select('core/block-editor').getBlocks() });
                    }
                })
                .catch((error) => {
                    console.error("Failed to update placeholder:", error);
                });
        }
    });
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

        const blockProps = useBlockProps();

        useEffect(() => {
            if (window.choiceGroups) {
                const textKeys = window.choiceGroups
                    .filter(group => group.field_type === 'text')
                    .map(group => ({ key: group.name, label: group.title }));
                setAvailableMetaKeys(textKeys);
            }
        }, []);

        const handleMetaKeyChange = ({ selectedItem }) => {
            const newMetaKey = selectedItem.key;
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
            // Fetch the placeholder description dynamically via the REST API
            apiFetch({ path: '/ace/v1/get-choices?field_type=text' }).then((response) => {
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
                            label="Select a Meta Field"
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
