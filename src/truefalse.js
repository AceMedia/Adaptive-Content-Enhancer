import { useEffect, useState } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl } from '@wordpress/components'; // Add SelectControl here


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
        trueFalseValue: { type: 'boolean', default: false }, // Change type to boolean
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
                setAvailableFields(window.trueFalseFields);
            }
        }, []);

        // Sync true/false value with post meta and block attributes
        useEffect(() => {
            const selectedFieldData = availableFields.find(field => field.name === selectedField);
            if (selectedFieldData) {
                const storedValue = trueFalseValue; // Use the value directly
                setAttributes({
                    trueFalseValue: storedValue,
                    trueFalseLabel: storedValue ? selectedFieldData.true_label : selectedFieldData.false_label,
                });
            }
        }, [trueFalseValue, selectedField, availableFields, setAttributes]);

        const handleToggleChange = (newValue) => {
            const selectedFieldData = availableFields.find(field => field.name === selectedField);
            if (!selectedFieldData) {
                console.error("Selected field data is missing for:", selectedField);
                return;
            }
            const label = newValue ? selectedFieldData.true_label : selectedFieldData.false_label;

            // Save the value (true or false) in post meta
            setMetaValue({
                ...metaValue,
                [metaKey]: newValue ? '1' : '0', // Store as a string
            });

            // Update block attributes for immediate rendering
            setAttributes({
                trueFalseValue: newValue,
                trueFalseLabel: label,
            });
        };

        const blockProps = useBlockProps();

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
                                    setAttributes({ selectedField: newField, trueFalseValue: false, trueFalseLabel: '' });
                                }}
                            />
                        )}

                        {/* Show the toggle control only when a field is selected */}
                        {selectedField && (
                            <ToggleControl
                                label={trueFalseLabel}
                                checked={trueFalseValue}
                                onChange={handleToggleChange}
                            />
                        )}
                    </div>
                ) : (
                    <p className="wp-block-ace-field-options-true-false-block wp-block-paragraph" style={{ margin: '0px', padding: '0px' }}>
                        {trueFalseLabel || 'Click to edit'}
                    </p>
                )}

                <InspectorControls>
                    <PanelBody title="True/False Field Settings">
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
                                setAttributes({ selectedField: newField, trueFalseValue: false, trueFalseLabel: '' });
                            }}
                        />
                    </PanelBody>
                </InspectorControls>
            </div>
        );
    },
    save: () => {
        return null; // All data is stored in post meta, no need to save additional content
    },
});
