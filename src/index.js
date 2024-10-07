import { useEffect, useState, useRef } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

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
        dropdownLabel: { type: 'string', default: '' }
    },
    supports: {
        html: false,
        styles: true,
        customClassName: false,
        typography: true,
    },
    edit: ({ attributes, setAttributes, isSelected }) => {
        const { selectedGroup, dropdownValue, dropdownLabel } = attributes;
        const [availableGroups, setAvailableGroups] = useState([]);
        const [groupOptions, setGroupOptions] = useState([]);
        const selectRef = useRef(null); // Ref for the SelectControl

        // Dynamically generate meta key based on selected group
        const metaKey = createLowercaseUnderscored(selectedGroup);

        // Fetch the post meta and keep it in sync
        const [metaValue, setMetaValue] = useEntityProp('postType', 'post', 'meta');

        // Fetch available groups and options from the backend (now each group is its own wp_option)
        useEffect(() => {
            if (Array.isArray(window.dropdownGroups)) {
                setAvailableGroups(window.dropdownGroups); // Assuming this still contains all groups as an array
            }
        }, []);

        // Update the group options when the selected group changes
        useEffect(() => {
            if (selectedGroup && selectedGroup !== '') {
                const group = availableGroups.find(group => group?.name === selectedGroup);
                if (group && Array.isArray(group.options)) {
                    setGroupOptions(group.options);
                } else {
                    setGroupOptions([]);
                }
            }
        }, [selectedGroup, availableGroups]);

        // Sync dropdown value with post meta and block attributes
        useEffect(() => {
            if (Array.isArray(metaValue[metaKey])) {
                const [storedLabel, storedValue] = metaValue[metaKey];
                setAttributes({
                    dropdownValue: storedValue,
                    dropdownLabel: storedLabel,
                });
            }
        }, [metaValue[metaKey], metaKey, groupOptions, setAttributes]);

      // Handle dropdown change, updating meta and syncing across all instances
      const handleDropdownChange = (newValue) => {
        const selectedOption = groupOptions.find(option => option.value === newValue);
        
        // Save the selected option value in post meta
        setMetaValue({
            ...metaValue,
            [metaKey]: newValue, // Directly store the selected value
        });
    
        // Update block attributes for immediate rendering
        setAttributes({
            dropdownValue: newValue,
            dropdownLabel: selectedOption ? selectedOption.label : '',
        });
    };
    
    // Sync dropdown value with post meta and block attributes
    useEffect(() => {
        // Get the stored value from meta
        const storedValue = metaValue[metaKey];
    
        if (storedValue) {
            const selectedOption = groupOptions.find(option => option.value === storedValue);
            setAttributes({
                dropdownValue: storedValue,
                dropdownLabel: selectedOption ? selectedOption.label : '',
            });
        }
    }, [metaValue[metaKey], metaKey, groupOptions, setAttributes]);

        // Auto-click the SelectControl when block is selected
        useEffect(() => {
            if (isSelected && selectRef.current) {
                // Find the native <select> element
                const selectEl = selectRef.current;
                console.log('Select element:', selectEl);
        
                // Focus the select element
                selectEl.parentElement.click();
        
                // This needs extra work to make it work properly.
                const event = new MouseEvent('mousedown', { bubbles: true, cancelable: true });
                selectEl.dispatchEvent(event);
            }
        }, [isSelected]);

        const blockProps = useBlockProps(); // Get the block props to manage class names

        return (
            <div {...blockProps}>
                {/* Show the dropdowns when the block is selected (focused), otherwise show the label */}
                {isSelected ? (
                    <div className="ace-dropdown-block" 
                    style={{ position: 'absolute', left: '0px', top: '0px', display: 'block', height: '100%', width: '100%' }}>
                        {/* Only display the group dropdown if no group is selected yet */}
                        {!selectedGroup && (
                            <SelectControl
                                value={selectedGroup}
                                options={[
                                    { label: 'Please select a group', value: '', disabled: true },
                                    ...availableGroups.map(group => ({
                                        label: group.name,
                                        value: group.name
                                    }))
                                ]}
                                onChange={(newGroup) => {
                                    setAttributes({ selectedGroup: newGroup, dropdownValue: '', dropdownLabel: '' });
                                }}
                            />
                        )}

                        {/* Show the options dropdown only when a group is selected */}
                        {selectedGroup && selectedGroup !== '' && groupOptions.length > 0 && (
                            <SelectControl
                                value={dropdownValue}
                                options={[
                                    { label: 'Please select an option', value: '', disabled: true },
                                    ...groupOptions.map(option => ({
                                        label: option.label,
                                        value: option.value
                                    }))
                                ]}
                                onChange={(newValue) => handleDropdownChange(newValue)}
                                ref={selectRef} // Attach the ref for auto-focus and auto-click
                            />
                        )}
                    </div>
                ) : (
                    // When the block is not selected, display only the label
                    <p className="wp-block-ace-field-options-dropdown-block wp-block-paragraph" style={{ margin: '0px', padding: '0px' }}>
                        {dropdownLabel || 'Click to edit'}
                    </p>
                )}

                <InspectorControls>
                    <PanelBody title="Dropdown Settings">
                        {/* Always display the group dropdown in the sidebar */}
                        <SelectControl
                            label="Select a Dropdown Group"
                            value={selectedGroup}
                            options={[
                                { label: 'Please select a group', value: '', disabled: true },
                                ...availableGroups.map(group => ({
                                    label: group.name,
                                    value: group.name
                                }))
                            ]}
                            onChange={(newGroup) => {
                                setAttributes({ selectedGroup: newGroup, dropdownValue: '', dropdownLabel: '' });
                            }}
                        />

                        {/* Sync the Inspector Control for the option selection */}
                        {selectedGroup && selectedGroup !== '' && groupOptions.length > 0 && (
                            <SelectControl
                                label="Select an Option"
                                value={dropdownValue}
                                options={groupOptions.map(option => ({
                                    label: option.label,
                                    value: option.value
                                }))}
                                onChange={(newValue) => handleDropdownChange(newValue)}
                            />
                        )}
                    </PanelBody>
                </InspectorControls>
            </div>
        );
    },
    save: () => {
        return null; // All data is stored in post meta, no need to save additional content
    }
});
