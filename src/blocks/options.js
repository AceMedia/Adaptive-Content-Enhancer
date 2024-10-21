import { useEffect, useState } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, CheckboxControl, RadioControl, ToggleControl, ToggleControl as SwitchControl, SelectControl, CustomSelectControl } from '@wordpress/components';

function createLowercaseUnderscored(string) {
    return string.toLowerCase().replace(/\s+/g, '_');
}

// Register the block
registerBlockType('ace-field-options/dropdown-block', {
    title: 'Ace Dropdown Block',
    icon: 'list-view',
    category: 'widgets',
    attributes: {
        selectedGroup: { type: 'string', default: '' },
        dropdownValue: { type: 'string', default: '' },
        dropdownLabel: { type: 'string', default: '' },
        selectedFieldType: { type: 'string', default: '' },
        trueFalseValue: { type: 'boolean', default: false },
        trueFalseLabel: { type: 'string', default: '' },
        showAsTextEditor: { type: 'boolean', default: false }, // New attribute to control editor display
        showAsTextFrontend: { type: 'boolean', default: false }, // New attribute to control frontend display
    },
    supports: {
        html: false,
        styles: true,
        customClassName: false,
        typography: true,
    },
    edit: ({ attributes, setAttributes, isSelected }) => {
        const { selectedGroup, dropdownValue, dropdownLabel, selectedFieldType, trueFalseValue, trueFalseLabel, showAsTextEditor, showAsTextFrontend } = attributes;
        const [availableGroups, setAvailableGroups] = useState([]);
        const [groupOptions, setGroupOptions] = useState([]);

        // Dynamically generate meta key based on selected group
        const metaKey = createLowercaseUnderscored(selectedGroup);

        // Fetch the post meta and keep it in sync
        const [metaValue, setMetaValue] = useEntityProp('postType', 'post', 'meta');

        // Fetch available groups and options from the backend
        useEffect(() => {
            if (Array.isArray(window.dropdownGroups)) {
                setAvailableGroups(window.dropdownGroups);
            }
        }, []);

        // Update the group options and field type when the selected group changes
        useEffect(() => {
            if (selectedGroup && selectedGroup !== '') {
                const group = availableGroups.find(group => group?.name === selectedGroup);
                if (group) {
                    setAttributes({ selectedFieldType: group.field_type });
                    if (Array.isArray(group.options)) {
                        setGroupOptions(group.options);
                    } else {
                        setGroupOptions([]);
                    }
                } else {
                    setGroupOptions([]);
                    setAttributes({ selectedFieldType: '' });
                }
            }
        }, [selectedGroup, availableGroups, setAttributes]);

        // Sync dropdown or true/false value with post meta and block attributes
        useEffect(() => {
            if (selectedFieldType === 'truefalse') {
                const selectedFieldData = availableGroups.find(field => field.name === selectedGroup);
                if (selectedFieldData) {
                    setAttributes({
                        trueFalseValue: trueFalseValue,
                        trueFalseLabel: trueFalseValue ? selectedFieldData.true_label : selectedFieldData.false_label,
                    });
                }
            } else if (metaValue && metaKey && Array.isArray(metaValue[metaKey])) {
                const [storedLabel, storedValue] = metaValue[metaKey];
                setAttributes({
                    dropdownValue: storedValue,
                    dropdownLabel: storedLabel,
                });
            }
        }, [trueFalseValue, selectedFieldType, selectedGroup, metaValue, metaKey, groupOptions, setAttributes]);

        useEffect(() => {
            // Make sure both controls are synced by setting attributes when dropdownValue changes
            if (dropdownValue !== attributes.dropdownValue) {
                setAttributes({ dropdownValue });
            }
        }, [dropdownValue, attributes.dropdownValue, setAttributes]);

        // Handle dropdown change, updating meta and syncing across all instances
        const handleDropdownChange = (newValue) => {
            const selectedOption = groupOptions.find(option => option.value === newValue);

            if (!selectedOption) {
                console.error("Selected option not found:", newValue);
                return;
            }

            setTimeout(() => {
                setMetaValue({
                    ...metaValue,
                    [metaKey]: selectedOption.value // Ensure saving just a string
                });

                setAttributes({
                    dropdownValue: newValue,
                    dropdownLabel: selectedOption.label,
                });
                console.log("Dropdown value updated to:", newValue);
            }, 10); // Introduce a 10ms delay
        };

        // Handle true/false toggle change, updating meta and syncing across all instances
        const handleToggleChange = (newValue) => {
            const selectedFieldData = availableGroups.find(field => field.name === selectedGroup);
            if (!selectedFieldData) {
                console.error("Selected field data is missing for:", selectedGroup);
                return;
            }
            const label = newValue ? selectedFieldData.true_label : selectedFieldData.false_label;

            setAttributes({
                trueFalseValue: newValue,
                trueFalseLabel: label,
            });
        };

        const blockProps = useBlockProps();

        // Render controls based on field type
        // Modify the `renderFieldControls` to only use `CustomSelectControl` in the editor:
// Modify the `renderFieldControls` to only use `CustomSelectControl` in the editor:
const renderFieldControls = () => {
    switch (selectedFieldType) {
        case 'truefalse':
            return (
                <ToggleControl
                    label={trueFalseLabel}
                    checked={trueFalseValue}
                    onChange={(newValue) => handleToggleChange(newValue)}
                />
            );
        case 'checkbox':
            return groupOptions.map((option, index) => (
                <CheckboxControl
                    key={index}
                    label={option.label}
                    checked={dropdownValue === option.value}
                    onChange={(isChecked) => handleDropdownChange(isChecked ? option.value : '')}
                />
            ));
        case 'radio':
        case 'buttons':
            return (
                <RadioControl
                    selected={dropdownValue}
                    options={groupOptions.map(option => ({
                        label: option.label,
                        value: option.value
                    }))}
                    onChange={(newValue) => handleDropdownChange(newValue)}
                />
            );
        default:
            return (
                <CustomSelectControl
                    label="Select an Option"
                    value={groupOptions.find(option => option.value === dropdownValue) ? {
                        key: dropdownValue,
                        name: groupOptions.find(option => option.value === dropdownValue)?.label
                    } : { key: '', name: 'Please select an option' }}
                    options={[
                        { name: 'Please select an option', key: '', disabled: true },
                        ...groupOptions.map(option => ({
                            name: option.label,
                            key: option.value,
                            style: { fontSize: '1rem', color: '#000' }, // Example styles
                        }))
                    ]}
                    onChange={({ selectedItem }) => handleDropdownChange(selectedItem.key)}
                    style={{ fontSize: '16px', padding: '8px' }}
                />
            );
    }
};

// Modify `InspectorControls` to explicitly use `SelectControl`:
return (
    <div {...blockProps}>
        {showAsTextEditor ? (
            <p>{dropdownLabel || trueFalseLabel || 'Click to edit'}</p> // Display as text if in text mode
        ) : (
            <div className="ace-dropdown-block">
                {/* Group selector */}
                {!selectedGroup && (
                    <SelectControl
                        label="Select a Group"
                        value={selectedGroup}
                        options={[
                            { label: 'Please select a group', value: '', disabled: true },
                            ...availableGroups.map(group => ({
                                label: group.name,
                                value: group.name
                            }))
                        ]}
                        onChange={(newGroup) => setAttributes({ selectedGroup: newGroup, dropdownValue: '', dropdownLabel: '', selectedFieldType: '' })}
                    />
                )}

                {/* Render dynamic controls based on field type */}
                {selectedGroup && selectedGroup !== '' && renderFieldControls()}
            </div>
        )}

        {/* Inspector Controls should always show full configuration, unaffected by showAsTextEditor */}
        <InspectorControls>
            <PanelBody title="Dropdown Settings">
                <SelectControl
                    label="Select an Option"
                    value={dropdownValue}
                    options={[
                        { label: 'Please select an option', value: '', disabled: true },
                        ...groupOptions.map(option => ({
                            label: option.label,
                            value: option.value
                        }))
                    ]}
                    onChange={(newValue) => handleDropdownChange(newValue)}
                />

                {selectedFieldType && (
                    <p><strong>Field Type:</strong> {selectedFieldType}</p>
                )}

                {/* Always display controls here */}
                {selectedGroup && selectedGroup !== '' && (
                    <SelectControl
                        label="Select an Option"
                        value={dropdownValue}
                        options={[
                            { label: 'Please select an option', value: '', disabled: true },
                            ...groupOptions.map(option => ({
                                label: option.label,
                                value: option.value
                            }))
                        ]}
                        onChange={(newValue) => handleDropdownChange(newValue)}
                    />
                )}
            </PanelBody>

            {/* New Inspector Controls */}
            <PanelBody title="Display Options">
                <SwitchControl
                    label="Show as Text in Editor"
                    checked={showAsTextEditor}
                    onChange={(newValue) => setAttributes({ showAsTextEditor: newValue })}
                />
                <SwitchControl
                    label="Show as Text on Frontend"
                    checked={showAsTextFrontend}
                    onChange={(newValue) => setAttributes({ showAsTextFrontend: newValue })}
                />
            </PanelBody>
        </InspectorControls>
    </div>
);


    },
    save: () => {
        return null;
    }
});
