import { useEffect, useState } from '@wordpress/element';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl } from '@wordpress/components';

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
        trueFalseValue: { type: 'boolean', default: false },
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

        // Fetch available true/false fields from the localized script
        useEffect(() => {
            if (Array.isArray(window.trueFalseFields)) {
                setAvailableFields(window.trueFalseFields);
            }
        }, []);

        // Sync true/false value with block attributes when the selected field or value changes
        useEffect(() => {
            const selectedFieldData = availableFields.find(field => field.name === selectedField);
            if (selectedFieldData) {
                setAttributes({
                    trueFalseValue: trueFalseValue,
                    trueFalseLabel: trueFalseValue ? selectedFieldData.true_label : selectedFieldData.false_label,
                });
            }
        }, [trueFalseValue, selectedField, availableFields, setAttributes]);

        // Handle the toggle change and update the meta value
        const handleToggleChange = (newValue) => {
            const selectedFieldData = availableFields.find(field => field.name === selectedField);
            if (!selectedFieldData) {
                console.error("Selected field data is missing for:", selectedField);
                return;
            }
            const label = newValue ? selectedFieldData.true_label : selectedFieldData.false_label;

            // Update attributes for rendering
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
                        {/* Field selection dropdown */}
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
                                // Reset value and label on field change
                                setAttributes({ selectedField: newField, trueFalseValue: false, trueFalseLabel: '' });
                            }}
                        />
                        {/* Toggle Control */}
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
