import { SelectControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';

const Edit = ({ attributes, setAttributes }) => {
    const { selectedGroup, dropdownValue, dropdownLabel } = attributes;
    const [groupOptions, setGroupOptions] = useState([]);
    const [availableGroups, setAvailableGroups] = useState([]);

    useEffect(() => {
        setAvailableGroups(window.dropdownGroups || []);
    }, []);

    useEffect(() => {
        if (selectedGroup) {
            const group = availableGroups.find(group => group.name === selectedGroup);
            if (group) {
                setGroupOptions(group.options);
            } else {
                setGroupOptions([]);
            }
        }
    }, [selectedGroup, availableGroups]);

    const handleDropdownChange = (newValue) => {
        const selectedOption = groupOptions.find(option => option.value === newValue);
        setAttributes({
            dropdownValue: newValue,
            dropdownLabel: selectedOption ? selectedOption.label : '', // Save the label correctly
        });
    };

    return (
        <>
            <InspectorControls>
                <SelectControl
                    label="Select a Dropdown Group"
                    value={selectedGroup}
                    options={availableGroups.map(group => ({
                        label: group.name,
                        value: group.name
                    }))}
                    onChange={(newGroup) => setAttributes({ selectedGroup: newGroup })}
                />

                {groupOptions.length > 0 && (
                    <SelectControl
                        label="Select an Option"
                        value={dropdownValue}
                        options={groupOptions.map(option => ({
                            label: option.label,
                            value: option.value
                        }))}
                        onChange={handleDropdownChange}
                    />
                )}
            </InspectorControls>

            {dropdownValue && (
                <p>Selected option: {dropdownLabel || dropdownValue}</p>
            )}
        </>
    );
};

export default Edit;