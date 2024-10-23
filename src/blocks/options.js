import { useEffect, useState, RichText } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps, BlockControls } from '@wordpress/block-editor';
import {  __experimentalText as Text, __experimentalHeading as Heading, Tip, PanelBody, CheckboxControl, RadioControl, ToggleControl, ToggleControl as SwitchControl, CustomSelectControl, Dropdown, MenuGroup, MenuItem, ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { edit } from '@wordpress/icons';

const paragraphBlockSettings = wp.blocks.getBlockSupport('core/paragraph', 'supports');

function createLowercaseUnderscored(string) {
    return string.toLowerCase().replace(/\s+/g, '_');
}

// Register the block
registerBlockType('adaptive-content-enhancer/option', {
    title: 'Option',
    icon: 'yes-alt',
    category: 'text',
    attributes: {
        selectedGroup: { type: 'string', default: '' },
        choiceValue: { type: 'string', default: '' },
        choiceLabel: { type: 'string', default: '' },
        selectedFieldType: { type: 'string', default: '' },
        description: { type: 'string', default: '' }, // Add description attribute
        trueFalseValue: { type: 'boolean' },
        trueFalseLabel: { type: 'string', default: '' },
        showAsTextEditor: { type: 'boolean', default: false }, 
        showLabelsInEditor: { type: 'boolean', default: true }, // New attribute to control editor display
        showAsInputOnFrontend: { type: 'boolean', default: false }, // New attribute to control frontend display
        showLabelsOnFrontend: { type: 'boolean', default: false }, // New attribute to control frontend display
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
    edit: ({ attributes, setAttributes }) => {
        const { selectedGroup, choiceValue, choiceLabel, selectedFieldType, trueFalseValue, trueFalseLabel, showAsTextEditor, showLabelsInEditor, showLabelsOnFrontend, showAsInputOnFrontend } = attributes;
        const [availableGroups, setAvailableGroups] = useState([]);
        const [groupOptions, setGroupOptions] = useState([]);

        const blockProps = useBlockProps({
            style: {
                textAlign: attributes.align,
                color: attributes.textColor,
                backgroundColor: attributes.backgroundColor,
                padding: attributes.padding,
                margin: attributes.margin,
                fontSize: attributes.fontSize,
            },
        });

        // Dynamically generate meta key based on selected group
        const metaKey = createLowercaseUnderscored(selectedGroup);

        // Fetch the post meta and keep it in sync
        const [metaValue, setMetaValue] = useEntityProp('postType', 'post', 'meta');

        // Fetch available groups and options from the backend
        useEffect(() => {
            if (Array.isArray(window.choiceGroups)) {
                setAvailableGroups(window.choiceGroups);
            }
        }, []);

        // Update the group options and field type when the selected group changes
        useEffect(() => {
            if (selectedGroup && selectedGroup !== '') {
                const group = availableGroups.find(group => group?.name === selectedGroup);
                if (group) {
                    setAttributes({ 
                        selectedFieldType: group.field_type, 
                        description: group.description || '' // Set description if available
                    });
                    // If there is existing meta data, adopt it immediately
                    if (metaValue && metaKey in metaValue) {
                        const storedValue = metaValue[attributes.selectedGroup];
        
                    console.log(storedValue);
      
                            // Handle non-boolean types (dropdowns)
                            const storedOption = group.options.find(option => option.value === storedValue || option.label === storedValue);
                            if (storedOption) {
                                setAttributes({
                                    choiceValue: storedValue,
                                    choiceLabel: storedOption.label
                                });
                            }
                        
                    }
        
                    // Set options for the group
                    if (Array.isArray(group.options)) {
                        setGroupOptions(group.options);
                    } else {
                        setGroupOptions([]);
                    }
                } else {
                    setGroupOptions([]);
                    setAttributes({ 
                        selectedFieldType: '', 
                        description: '' // Clear description if group not found
                    });
                }
            }
        }, [selectedGroup, availableGroups, metaValue, metaKey, setAttributes]);
        

        useEffect(() => {
            if (selectedFieldType === 'truefalse' && selectedGroup) {
                // Fetch all blocks and find the ones that match the selectedGroup
                const allBlocks = wp.data.select('core/block-editor').getBlocks();
                const matchingBlocks = allBlocks.filter(block => 
                    block.attributes.selectedGroup === selectedGroup &&
                    block.attributes.trueFalseValue !== undefined
                );
        
                // If there are matching blocks, take the first one and sync its value
                if (matchingBlocks.length > 0) {
                    const { trueFalseValue, trueFalseLabel } = matchingBlocks[0].attributes;
        
                    setAttributes({
                        trueFalseValue,
                        trueFalseLabel,
                    });
                }
            }
        }, [selectedGroup, selectedFieldType, setAttributes]);
        

        useEffect(() => {
            // Make sure both controls are synced by setting attributes when choiceValue changes
            if (choiceValue !== attributes.choiceValue) {
                setAttributes({ choiceValue });
            }
        }, [choiceValue, attributes.choiceValue, setAttributes]);

        // Handle dropdown change, updating meta and syncing across all instances
        // Handle dropdown change, updating meta and syncing across all instances
const handleChoicesChange = (newValue) => {
    const selectedFieldData = availableGroups.find(field => field.name === selectedGroup);
    if (!selectedFieldData) {
        return;
    }

    // Handle true/false logic
    if (selectedFieldType === 'truefalse') {
        const label = newValue === '1' 
            ? selectedFieldData.options?.find(option => option.value === '1')?.label 
            : selectedFieldData.options?.find(option => option.value === '0')?.label;

        // Sync other blocks with the same selectedGroup
        const allBlocks = wp.data.select('core/block-editor').getBlocks();
        allBlocks.forEach(block => {
            if (block.attributes.selectedGroup === selectedGroup) {
                // Update attributes of other blocks to reflect the changes
                wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, {
                    trueFalseValue: newValue === '1',
                    trueFalseLabel: label,
                });
            }
        });

        // Update meta and block attributes
        setMetaValue({
            ...metaValue,
            [metaKey]: newValue, // Save as '1' or '0'
        });

        setAttributes({
            trueFalseValue: newValue === '1',
            trueFalseLabel: label,
        });

        return;
    }

    // Handle other field types
    const selectedOption = groupOptions.find(option => option.value === newValue);
    if (!selectedOption) {
        return;
    }

    // Sync other blocks with the same selectedGroup
    const allBlocks = wp.data.select('core/block-editor').getBlocks();
    allBlocks.forEach(block => {
        if (block.attributes.selectedGroup === selectedGroup) {
            wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, {
                choiceValue: selectedOption.value,
                choiceLabel: selectedOption.label,
            });
        }
    });

    // Update meta and block attributes for dropdown
    setMetaValue({
        ...metaValue,
        [metaKey]: selectedOption.value, // Save as a string
    });

    setAttributes({
        choiceValue: newValue,
        choiceLabel: selectedOption.label,
    });
};

        

        // Render controls based on field type
        const renderFieldControls = (context) => {
            switch (selectedFieldType) {
                case 'truefalse':
                    return (
                        <SwitchControl
                            label={trueFalseLabel}
                            checked={trueFalseValue}
                            onChange={(newValue) => handleChoicesChange(newValue ? '1' : '0')}
                        />
                    );
                case 'checkbox':
                    return groupOptions.map((option, index) => (
                        <CheckboxControl
                            key={index}
                            label={option.label}
                            checked={choiceValue === option.value}
                            onChange={(isChecked) => handleChoicesChange(isChecked ? option.value : '')}
                        />
                    ));
                case 'radio':
                case 'buttons':
                    return (
                        <RadioControl
                            selected={choiceValue}
                            options={groupOptions.map(option => ({
                                label: option.label,
                                value: option.value
                            }))}
                            onChange={(newValue) => handleChoicesChange(newValue)}
                        />
                    );
                default:
                    const label = context === 'editor' ? 'Select an Option in Sidebar' : selectedGroup;
                    return (
                        <>
                            <CustomSelectControl
                                style={{ fontSize: 'inherit', padding: '.5em', height: 'auto', borderRadius: '.25em' }}
                                label={context === 'editor' ? label : (showLabelsInEditor ? label : null)}
                                value={groupOptions.find(option => option.value === choiceValue) ? {
                                    key: choiceValue,
                                    name: groupOptions.find(option => option.value === choiceValue)?.label
                                } : { key: '', name: 'Please select an option' }}
                                options={[
                                    ...groupOptions.map(option => ({
                                        name: option.label,
                                        key: option.value,
                                        style: { fontSize: '1em', color: '#000' },
                                    }))
                                ]}
                                onChange={({ selectedItem }) => handleChoicesChange(selectedItem.key)}
                            />
                        </>
                    );
            }
        };

       // Add toolbar controls based on selectedFieldType
const toolbarControls = (
    <ToolbarGroup>
        {selectedFieldType === 'truefalse' ? (
            // Render a toggle switch for truefalse fields
            <ToggleControl
                label={trueFalseLabel || "Toggle Option"}
                checked={trueFalseValue}
                onChange={(newValue) => {
                    handleChoicesChange(newValue ? '1' : '0')
                    setAttributes({ trueFalseValue: newValue });
                }}
            />
        ) : (
            // Otherwise, render the dropdown
            <Dropdown
                popoverProps={{ position: 'bottom center' }}
                renderToggle={({ isOpen, onToggle }) => (
                    <ToolbarButton
                        onClick={onToggle}
                        aria-expanded={isOpen}
                        icon={edit}
                        label="Select Option"
                        showTooltip={false} // Disable tooltip
                        text="" // Add text to the button
                    >
                        {choiceLabel || "Select Option"}
                    </ToolbarButton>
                )}
                renderContent={() => (
                    <MenuGroup>
                        {groupOptions.map((option) => (
                            <MenuItem
                                key={option.value}
                                onClick={() => handleChoicesChange(option.value)}
                                isSelected={choiceValue === option.value}
                            >
                                {option.label}
                            </MenuItem>
                        ))}
                    </MenuGroup>
                )}
            />
        )}
    </ToolbarGroup>



);



        return (
            <>
                <BlockControls>
                    {toolbarControls}
                </BlockControls>
                {showAsTextEditor ? (
                    <>
                        <>{choiceLabel || trueFalseLabel || 'Click to edit'}</> {/* Display as text if in text mode */}
                    </>
                ) : (
                    <div className="content-enhancer-choices-choices-block">
                        {/* Group selector */}
                        {!selectedGroup && (
                            <CustomSelectControl
                                label="Select an Option"
                                value={availableGroups.find(group => group.name === selectedGroup) ? {
                                    key: selectedGroup,
                                    name: availableGroups.find(group => group.name === selectedGroup)?.title,
                                    description: availableGroups.find(group => group.name === selectedGroup)?.field_type // Show field type as subtext
                                } : { key: '', name: 'Please select an Option' }}
                                options={[
                                    ...availableGroups.map(group => ({
                                        name: group.title,
                                        key: group.name,
                                        description: group.field_type // Show field type as subtext
                                    }))
                                ]}
                                onChange={({ selectedItem }) => setAttributes({
                                    selectedGroup: selectedItem.key,
                                    choiceValue: '',
                                    choiceLabel: '',
                                    selectedFieldType: availableGroups.find(group => group.name === selectedItem.key)?.field_type || ''
                                })}
                            />
                        )}
                        {/* Render dynamic controls based on field type */}
                        {selectedGroup && selectedGroup !== '' && renderFieldControls()}
                    </div>
                )}
                <InspectorControls>
                    <PanelBody title="Dropdown Settings">
                        <CustomSelectControl
                            label="Select an Option"
                            value={availableGroups.find(group => group.name === selectedGroup) ? {
                                key: selectedGroup,
                                name: (
                                    <>
                                        {availableGroups.find(group => group.name === selectedGroup)?.title}          
                                    </>
                                ),
                            } : { key: '', name: 'Please select an Option' }}
                            options={[
                                ...availableGroups.map(group => ({
                                    name: (
                                        <>
                                            {group.title}
                                        </>
                                    ),
                                    key: group.name,
                                    description: group.description,
                                    hint: group.field_type
                                }))
                            ]}
                            onChange={({ selectedItem }) => setAttributes({
                                selectedGroup: selectedItem.key,
                                choiceValue: '',
                                choiceLabel: '',
                                selectedFieldType: availableGroups.find(group => group.name === selectedItem.key)?.field_type || ''
                            })}
                        />
                        {selectedFieldType && (
                            <Text style={{ display: 'block', margin: '1em 0px' }}>{attributes.description}</Text>
                        )}
                        {/* Always display controls here */}
                        {selectedGroup && selectedGroup !== '' && renderFieldControls('editor')}
                    </PanelBody>
                    <PanelBody title="Editor Options">
                        <SwitchControl
                            label="Show as Text in Editor"
                            checked={showAsTextEditor}
                            onChange={(newValue) => setAttributes({ showAsTextEditor: newValue })}
                        />
                        {showAsTextEditor ? null : (
                            <SwitchControl
                                label="Show Labels in Editor"
                                checked={showLabelsInEditor}
                                onChange={(newValue) => setAttributes({ showLabelsInEditor: newValue })}
                            />
                        )}
                    </PanelBody>
                    <PanelBody title="Frontend Options">
                        <SwitchControl
                            label="Show as Input on Frontend"
                            checked={showAsInputOnFrontend}
                            onChange={(newValue) => setAttributes({ showAsInputOnFrontend: newValue })}
                        />
                        {showAsInputOnFrontend ? (
                            <SwitchControl
                                label="Show Labels on Frontend"
                                checked={showLabelsOnFrontend}
                                onChange={(newValue) => setAttributes({ showLabelsOnFrontend: newValue })}
                            />
                        ) : null}
                    </PanelBody>
                </InspectorControls>
            </>
        );
    },
    save: () => {
        return null;
    }
});
