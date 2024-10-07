import { useEffect, useState } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

function createLowercaseUnderscored(string) {
    return string.toLowerCase().replace(/\s+/g, '_');
}

// Register the block
registerBlockType('ace-field-options/true-false-block', {
    title: 'Ace True/False Block',
    icon: 'yes-alt',
    category: 'widgets',
    attributes: {
        selectedField: { type: 'string', default: '' },
        trueFalseValue: { type: 'string', default: '' },
        trueFalseLabel: { type: 'string', default: '' },
    },
    supports: {
        html: false,
        styles: true,
        customClassName: false,
        typography: true,
    },
    edit: ({ attributes, setAttributes, isSelected }) => {
        const { selectedField, trueFalseValue, trueFalseLabel } = attributes;
        const [availableFields, setAvailableFields] = useState([]);

        // Dynamically generate meta key based on selected field
        const metaKey = createLowercaseUnderscored(selectedField);

        // Fetch the post meta and keep it in sync
        const [metaValue, setMetaValue] = useEntityProp('postType', 'post', 'meta');

        // Fetch available true/false fields from the backend
        useEffect(() => {
            if (Array.isArray(window.trueFalseFields)) {
                setAvailableFields(window.trueFalseFields); // Assuming this still contains all true/false fields as an array
            }
        }, []);

        // Sync true/false value with post meta and block attributes
        useEffect(() => {
            if (metaValue[metaKey]) {
                const storedValue = String(metaValue[metaKey]); // Ensure value is string
                const selectedFieldData = availableFields.find(field => field.name === selectedField);
                const label = storedValue === '1' ? selectedFieldData?.true_label : selectedFieldData?.false_label;

                // Debugging state to check meta and label syncing
                console.log("Meta Key:", metaKey, "Stored Value:", storedValue, "Label:", label);

                setAttributes({
                    trueFalseValue: storedValue,
                    trueFalseLabel: label || '',
                });
            }
        }, [metaValue, metaKey, setAttributes, availableFields, selectedField]);

        const handleTrueFalseChange = (newValue) => {
            const selectedFieldData = availableFields.find(field => field.name === selectedField);
            if (!selectedFieldData) {
                console.error("Selected field data is missing for:", selectedField);
                return;
            }
            const label = newValue === '1' ? selectedFieldData.true_label : selectedFieldData.false_label;
        
            // Save the value (1 or 0) in post meta
            setMetaValue({
                ...metaValue,
                [metaKey]: String(newValue), // Store as a string
            });
        
            // Update block attributes for immediate rendering
            setAttributes({
                trueFalseValue: newValue,
                trueFalseLabel: label,
            });
        };
        

        const blockProps = useBlockProps(); // Get the block props to manage class names

        return (
            <div {...blockProps}>
                {isSelected ? (
                    <div className="ace-true-false-block">
                        {/* Only display the field dropdown if no field is selected yet */}
                        {!selectedField && (
                            <SelectControl
                                label="Select a True/False Field"
                                value={selectedField}
                                options={[
                                    { label: 'Please select a field', value: '', disabled: true },
                                    ...availableFields.map(field => ({
                                        label: field.name,
                                        value: field.name,
                                    })),
                                ]}
                                onChange={(newField) => {
                                    setAttributes({ selectedField: newField, trueFalseValue: '', trueFalseLabel: '' });
                                }}
                            />
                        )}

                        {/* Show the true/false dropdown only when a field is selected */}
                        {selectedField && (
                            <SelectControl
                                label="Select True or False"
                                value={trueFalseValue}
                                options={[
                                    { label: availableFields.find(field => field.name === selectedField)?.true_label || 'True', value: '1' },
                                    { label: availableFields.find(field => field.name === selectedField)?.false_label || 'False', value: '0' },
                                ]}
                                onChange={handleTrueFalseChange}
                            />
                        )}
                    </div>
                ) : (
                    // When the block is not selected, display only the label (e.g., Yes/No)
                    <p className="wp-block-ace-field-options-true-false-block wp-block-paragraph" style={{ margin: '0px', padding: '0px' }}>
                        {trueFalseLabel || 'Click to edit'}
                    </p>
                )}

                <InspectorControls>
                    <PanelBody title="True/False Field Settings">
                        {/* Always display the field dropdown in the sidebar */}
                        <SelectControl
                            label="Select a True/False Field"
                            value={selectedField}
                            options={[
                                { label: 'Please select a field', value: '', disabled: true },
                                ...availableFields.map(field => ({
                                    label: field.name,
                                    value: field.name,
                                })),
                            ]}
                            onChange={(newField) => {
                                setAttributes({ selectedField: newField, trueFalseValue: '', trueFalseLabel: '' });
                            }}
                        />

                        {/* Sync the Inspector Control for the true/false selection */}
                        {selectedField && (
                            <SelectControl
                                label="Select True or False"
                                value={trueFalseValue}
                                options={[
                                    { label: availableFields.find(field => field.name === selectedField)?.true_label || 'True', value: '1' },
                                    { label: availableFields.find(field => field.name === selectedField)?.false_label || 'False', value: '0' },
                                ]}
                                onChange={handleTrueFalseChange}
                            />
                        )}
                    </PanelBody>
                </InspectorControls>
            </div>
        );
    },
    save: () => {
        return null; // All data is stored in post meta, no need to save additional content
    },
});