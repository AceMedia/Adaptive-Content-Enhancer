import { SelectControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';

const Edit = ({ attributes, setAttributes }) => {
    const { selectedGroup, dropdownValue } = attributes;
    const [groupOptions, setGroupOptions] = useState([]);
    const [availableGroups, setAvailableGroups] = useState([]);

    // Fetch the available dropdown groups from the localized script (passed from PHP).
    useEffect(() => {
        setAvailableGroups(window.dropdownGroups || []);
    }, []);

    // Update the options when the selected group changes.
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

    return (
        <>
            <InspectorControls>
                {/* Dropdown to select which group to use */}
                <SelectControl
                    label="Select a Dropdown Group"
                    value={selectedGroup}
                    options={availableGroups.map(group => ({
                        label: group.name,
                        value: group.name
                    }))}
                    onChange={(newGroup) => setAttributes({ selectedGroup: newGroup })}
                />

                {/* Dropdown to select a value from the selected group */}
                {groupOptions.length > 0 && (
                    <SelectControl
                        label="Select an Option"
                        value={dropdownValue}
                        options={groupOptions.map(option => ({
                            label: option.label,
                            value: option.value
                        }))}
                        onChange={(newValue) => setAttributes({ dropdownValue: newValue })}
                    />
                )}
            </InspectorControls>

            {/* Display the selected dropdown option in the block editor */}
            {dropdownValue && (
                <p>Selected option: {dropdownValue}</p>
            )}
        </>
    );
};

export default Edit;
