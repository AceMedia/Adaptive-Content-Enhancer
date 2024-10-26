import { useEffect, useState, RichText } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps, BlockControls } from '@wordpress/block-editor';
import {  __experimentalText as Text, __experimentalHeading as Heading, Tip, PanelBody, CheckboxControl, RadioControl, ToggleControl, ToggleControl as SwitchControl, CustomSelectControl, Dropdown, MenuGroup, MenuItem, ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { edit, title } from '@wordpress/icons';

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
        trueFalseValue: { type: 'string', default: '' },
        title: { type: 'string', default: '' },
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
                // Filter out groups where the field type is "text"
                const filteredGroups = window.choiceGroups.filter(group => group.field_type !== 'text');
                setAvailableGroups(filteredGroups);
            }
        }, []);

        // Extend useEffect to fetch and set truefalse meta values correctly
        useEffect(() => {
            if (selectedGroup && selectedGroup !== '') {
                const group = availableGroups.find(group => group?.name === selectedGroup);
                if (group) {
                    setAttributes({ 
                        selectedFieldType: group.field_type, 
                        description: group.description || '',
                        title: group.title || '' // Ensure title is set
                    });
        
                    // If there is existing meta data, adopt it immediately
                    if (metaValue && metaKey in metaValue) {
                        const storedValue = metaValue[attributes.selectedGroup];
                        const storedOption = group.options.find(option => option.value === storedValue || option.label === storedValue);
                        if (storedOption) {
                            setAttributes({
                                choiceValue: storedValue,
                                choiceLabel: storedOption.label
                            });
                        }
        
                        // Handle true/false specifically
                        if (group.field_type === 'truefalse') {
                            const isTrue = storedValue === '1';
                            const label = group.options.find(option => option.value === storedValue)?.label;
                            setAttributes({
                                trueFalseValue: storedValue,
                                trueFalseLabel: label
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
                        description: '',
                        title: '' // Clear title if group not found
                    });
                }
            }
        }, [selectedGroup, availableGroups, metaValue, metaKey, setAttributes]);
        
        

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
            const selectedGroupTitle = availableGroups.find(group => group.name === selectedGroup)?.title || 'Select an Option in Sidebar';
            
            switch (selectedFieldType) {
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
                case 'truefalse':
                    // Add support for 'truefalse' toggle controls
                    return (
                        <ToggleControl
                            label={trueFalseLabel || "Toggle Option"}
                            checked={trueFalseValue === '1'} // Ensure the toggle reflects '1' or '0'
                            onChange={(newValue) => handleChoicesChange(newValue ? '1' : '0')}
                        />
                    );
                default:
                    return (
                        <>
                            <CustomSelectControl
                                style={{ fontSize: 'inherit', padding: '.5em', height: 'auto', borderRadius: '.25em' }}
                                label={context === 'editor' ? selectedGroupTitle : (showLabelsInEditor ? selectedGroupTitle : null)}
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
