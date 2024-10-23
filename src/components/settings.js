import { useState, useEffect } from '@wordpress/element';
import { render } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { 
    TextControl, 
    SelectControl, 
    TextareaControl, 
    ToggleControl, 
    Button, 
    Flex, 
    FlexItem, 
    Card, 
    CardHeader,
    CardBody, 
    CardFooter,
    Panel,
    PanelBody,
    PanelRow
} from '@wordpress/components';
import { more } from '@wordpress/icons';

function createLowercaseUnderscored(string) {
    return string.toLowerCase().replace(/\s+/g, '_');
}

const AdaptiveContentEnhancer = () => {
    const [choices, setChoices] = useState([]);
    const [editKey, setEditKey] = useState(null);

    useEffect(() => {
        // Fetch options data from the backend
        apiFetch({ path: '/ace/v1/get-choices' })
            .then((fetchedChoices) => {
                const updatedChoices = fetchedChoices.map(choice => {
                    if (choice.field_type === 'truefalse') {
                        return {
                            ...choice,
                            true_label: choice.options?.find(option => option.value === '1')?.label || 'True',
                            false_label: choice.options?.find(option => option.value === '0')?.label || 'False'

                            
                        };
                    }
                    return choice;
                });
                setChoices(updatedChoices);
            })
            .catch((error) => console.error('Error fetching choices:', error));
    }, []);

    const handleSave = () => {
        const payload = {
            choices: choices.map(choice => ({
                name: choice.name, // Hidden key
                title: choice.title, // Editable display title
                field_type: choice.field_type,
                description: choice.description,
                options: choice.options ? choice.options.map(option => ({
                    label: option.label,
                    value: option.value,
                })) : [],
                show_in_rest: choice.show_in_rest ? 1 : 0
            })),
            _wpnonce: aceAjax.nonce, // Assuming nonce is localized
        };
    
        // Save settings via the custom REST API endpoint
        apiFetch({
            path: '/ace/v1/save-options',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        }).then((response) => {
            if (response.success) {
                setChoices(choices.map(choice => ({ ...choice, isNew: false })));
                alert('Settings saved successfully!');
            } else {
                alert('Failed to save settings. Please try again.');
                console.error('Error:', response.error);
            }
        }).catch((error) => {
            console.error('Error saving settings:', error);
            alert('Failed to save settings. Please try again.');
        });
    };

    const handleAddChoiceGroup = () => {
        const newTitle = `Group ${choices.length + 1}`;
        setChoices([
            ...choices,
            {
                name: createLowercaseUnderscored(newTitle), // Create hidden key
                title: newTitle, // Set default title
                field_type: 'select',
                description: '',
                options: [],
                show_in_rest: false,
                isNew: true, // Add isNew property to identify this as a new group
            },
        ]);
    };
    

    const updateChoice = (index, field, value) => {
        const updatedChoices = [...choices];
        updatedChoices[index][field] = value;
        
        // Automatically add default options when the field type is set to 'truefalse'
        if (field === 'field_type' && value === 'truefalse') {
            updatedChoices[index].options = [
                { label: 'Trudddde', value: '1' },
                { label: 'False', value: '0' }
            ];
        } else if (field === 'field_type' && value !== 'truefalse') {
            // Clear options if it's not truefalse
            updatedChoices[index].options = [];
        }
    
        // Set name based on the title only if the group is new
        if (field === 'title' && updatedChoices[index].isNew) {
            updatedChoices[index].name = createLowercaseUnderscored(value);
        }
    
        setChoices(updatedChoices);
    };
    

    const updateChoiceCheckbox = (index, checked) => {
        const updatedChoices = [...choices];
        updatedChoices[index].show_in_rest = checked;
        setChoices(updatedChoices);
    };

    const updateOption = (groupIndex, optionIndex, field, value) => {
        const updatedChoices = [...choices];
        updatedChoices[groupIndex].options[optionIndex][field] = value;
        setChoices(updatedChoices);
    };

    const addOption = (groupIndex) => {
        const updatedChoices = [...choices];
        updatedChoices[groupIndex].options.push({ label: '', value: '' });
        setChoices(updatedChoices);
    };

    const removeOption = (groupIndex, optionIndex) => {
        const updatedChoices = [...choices];
        updatedChoices[groupIndex].options.splice(optionIndex, 1);
        setChoices(updatedChoices);
    };

    const confirmAndRemoveGroup = (index, fieldType, groupName) => {
        if (window.confirm('Are you sure you want to remove this group?')) {
            const processedGroupName = createLowercaseUnderscored(groupName);
            const optionName = 'choices_' + processedGroupName;
    
            apiFetch({
                path: '/ace/v1/delete-option',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    option_name: optionName,
                    _wpnonce: aceAjax.nonce,
                }),
            }).then((response) => {
                if (response.success) {
                    alert('Group removed successfully!');
                    setChoices(choices.filter((_, i) => i !== index));
                } else {
                    alert('Failed to remove group. Please try again.');
                    console.error('Error:', response.error);
                }
            }).catch((error) => {
                console.error('Error removing group:', error);
                alert('An error occurred. Please try again.');
            });
        }
    };

    const renderChoicesTable = () => {
        const [openIndexes, setOpenIndexes] = useState({});
    
        const toggleOpen = (index) => {
            setOpenIndexes((prev) => ({
                ...prev,
                [index]: !prev[index],
            }));
        };
    
        return (
            <div>
                <h2>Option Groups</h2>
                {choices.map((choice, index) => (
                    <Card key={index} style={{ width: '100%', marginBottom: '20px' }}>
                        <CardHeader
                            onClick={() => toggleOpen(index)}
                            style={{ cursor: 'pointer' }}
                        >
                            <strong>{choice.title || `Group ${index + 1}`}</strong>
                        </CardHeader>
                        {openIndexes[index] && (
                            <CardBody>
                                <TextControl
                                    label="Group Title"
                                    value={choice.title}
                                    onChange={(value) => updateChoice(index, 'title', value)}
                                />
                                <TextControl
                                    label="Group Name"
                                    value={`${choice.name}`}
                                    onChange={(value) => updateChoice(index, 'name', value)}
                                    disabled={!choice.isNew}
                                />
                                <SelectControl
                                    label="Field Type"
                                    value={choice.field_type}
                                    options={[
                                        { label: 'Select', value: 'select' },
                                        { label: 'Checkbox', value: 'checkbox' },
                                        { label: 'True/False', value: 'truefalse' },
                                        { label: 'Radio', value: 'radio' },
                                        { label: 'Buttons', value: 'buttons' },
                                    ]}
                                    onChange={(value) => updateChoice(index, 'field_type', value)}
                                />
                                <TextareaControl
                                    label="Description"
                                    value={choice.description}
                                    onChange={(value) => updateChoice(index, 'description', value)}
                                />
                                <ToggleControl
                                    label="Show in REST API"
                                    checked={choice.show_in_rest}
                                    onChange={(value) => updateChoiceCheckbox(index, value)}
                                />
    
                        
                                    <div style={{ marginTop: '16px' }}>
                                        <strong>Options:</strong>
                                        {choice.options.map((option, optionIndex) => (
                                            <div key={optionIndex} style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                <TextControl
                                                    placeholder="Option Label"
                                                    value={option.label}
                                                    onChange={(value) => updateOption(index, optionIndex, 'label', value)}
                                                />
                                                <TextControl
                                                    placeholder="Option Value"
                                                    value={option.value}
                                                    onChange={(value) => updateOption(index, optionIndex, 'value', value)}
                                                />
                                                <Button
                                                    isDestructive
                                                    onClick={() => removeOption(index, optionIndex)}
                                                >
                                                    Remove
                                                </Button>
                                            </div>
                                        ))}
                                        <Button isPrimary onClick={() => addOption(index)}>Add Option</Button>
                                    </div>
    
                                <Button
                                    isDestructive
                                    onClick={() => confirmAndRemoveGroup(index, choice.field_type, choice.name)}
                                    style={{ marginTop: '16px' }}
                                >
                                    Remove Group
                                </Button>
                            </CardBody>
                        )}
                    </Card>
                ))}
                <Button isPrimary onClick={handleAddChoiceGroup}>Add Group</Button>
            </div>
        );
    };

    return (
        <div className="wrap">
            <h1>Adaptive Content Enhancer Settings</h1>
            {renderChoicesTable()}
            <button className="button-primary" onClick={handleSave}>Save Settings</button>
        </div>
    );
};

// Render the component to the admin settings page
document.addEventListener('DOMContentLoaded', () => {
    const settingsElement = document.getElementById('adaptive-content-enhancer-settings');
    if (settingsElement) {
        render(<AdaptiveContentEnhancer />, settingsElement);
    }
});

export default AdaptiveContentEnhancer;
