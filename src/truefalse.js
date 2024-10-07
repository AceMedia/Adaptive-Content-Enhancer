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
        const { selectedField, trueFalseValue } = attributes;
        const [availableFields, setAvailableFields] = useState([]);

        // Fetch available true/false fields from the backend
        useEffect(() => {
            if (Array.isArray(window.trueFalseFields)) {
                setAvailableFields(window.trueFalseFields);
            }
        }, []);

        // Sync the true/false value with the selected field and post meta
        useEffect(() => {
            const selectedFieldData = availableFields.find(field => field.name === selectedField);
            if (selectedFieldData) {
                const storedValue = trueFalseValue; // Use the stored value directly
                const label = storedValue === '1' ? selectedFieldData.true_label : selectedFieldData.false_label;
                setAttributes({
                    trueFalseLabel: label,
                });
            }
        }, [trueFalseValue, selectedField, availableFields, setAttributes]);

        const handleTrueFalseChange = (newValue) => {
            const selectedFieldData = availableFields.find(field => field.name === selectedField);
            if (!selectedFieldData) {
                console.error("Selected field data is missing for:", selectedField);
                return;
            }
            const label = newValue === '1' ? selectedFieldData.true_label : selectedFieldData.false_label;

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
                    <p className="wp-block-ace-field-options-true-false-block wp-block-paragraph" style={{ margin: '0px', padding: '0px' }}>
                        {attributes.trueFalseLabel || 'Click to edit'}
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
                                setAttributes({ selectedField: newField, trueFalseValue: '', trueFalseLabel: '' });
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
