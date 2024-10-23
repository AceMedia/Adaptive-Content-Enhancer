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
    const [trueFalseFields, setTrueFalseFields] = useState([]);
    const [editKey, setEditKey] = useState(null);

    useEffect(() => {
        // Fetch options data from the backend
        apiFetch({ path: '/ace/v1/get-choices' })
            .then(setChoices)
            .catch((error) => console.error('Error fetching choices:', error));

        // Fetch true/false fields from the backend
        apiFetch({ path: '/ace/v1/get-truefalse-fields' })
            .then(setTrueFalseFields)
            .catch((error) => console.error('Error fetching true/false fields:', error));
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
            true_false_fields: trueFalseFields.map(field => ({
                name: field.name,
                title: field.title, // Include the title
                true_label: field.true_label,
                false_label: field.false_label,
                show_in_rest: field.show_in_rest ? 1 : 0
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
            },
        ]);
    };

    const handleAddTrueFalseField = () => {
        setTrueFalseFields([
            ...trueFalseFields,
            {
                name: '',
                title: '',
                true_label: 'True',
                false_label: 'False',
                show_in_rest: false,
            },
        ]);
    };

    const updateChoice = (index, field, value) => {
        const updatedChoices = [...choices];
        updatedChoices[index][field] = value;
        if (field === 'title' && !updatedChoices[index].name) {
            updatedChoices[index].name = createLowercaseUnderscored(value);
        }
        setChoices(updatedChoices);
    };



// Modified to handle checkbox for 'show_in_rest'
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

    const updateTrueFalseField = (index, field, value) => {
        const updatedFields = [...trueFalseFields];
        updatedFields[index][field] = value;
        if (field === 'title' && !updatedFields[index].name) {
            updatedFields[index].name = createLowercaseUnderscored(value);
        }
        setTrueFalseFields(updatedFields);
    };

    // Modified to handle checkbox for 'show_in_rest'
const updateTrueFalseCheckbox = (index, checked) => {
    const updatedFields = [...trueFalseFields];
    updatedFields[index].show_in_rest = checked;
    setTrueFalseFields(updatedFields);
};

    const confirmAndRemoveGroup = (index, fieldType, groupName) => {
        if (window.confirm('Are you sure you want to remove this group?')) {
            // Convert groupName to lowercase underscored format
            const processedGroupName = createLowercaseUnderscored(groupName);
            const optionName = fieldType === 'truefalse' ? 'truefalse_' + processedGroupName : 'choices_' + processedGroupName;
    
            apiFetch({
                path: '/ace/v1/delete-option',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', // Ensure proper JSON content type
                },
                body: JSON.stringify({
                    option_name: optionName,
                    _wpnonce: aceAjax.nonce, // Pass the nonce here as well
                }),
            }).then((response) => {
                if (response.success) {
                    alert('Group removed successfully!');
                    if (fieldType === 'truefalse') {
                        setTrueFalseFields(trueFalseFields.filter((_, i) => i !== index));
                    } else {
                        setChoices(choices.filter((_, i) => i !== index));
                    }
                } else {
                    alert('Failed to remove group. Please try again.');
                    console.error('Error:', response.error); // Log the exact error for debugging
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
                                value={`choices_${choice.name}`}
                                onChange={(value) => updateChoice(index, 'name', value)}
                                disabled='true'
                            />
                                <SelectControl
                                    label="Field Type"
                                    value={choice.field_type}
                                    options={[
                                        { label: 'Select', value: 'select' },
                                        { label: 'Checkbox', value: 'checkbox' },
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
    
    const renderTrueFalseFieldsTable = () => (
        <div>
            <h2>True/False Fields</h2>
            <table className="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Field Title</th> {/* New title column */}
                        <th>Field Name</th>
                        <th>Label for True</th>
                        <th>Label for False</th>
                        <th>Show in REST API</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    {trueFalseFields.map((field, index) => (
                        <tr key={index}>
                            <td>
                                <TextControl
                                    placeholder="Field Title"
                                    value={field.title} // Handle title
                                    onChange={(value) => updateTrueFalseField(index, 'title', value)}
                                />
                            </td>
                            <td>
                                <TextControl
                                    placeholder="Field Name"
                                    value={`truefalse_${field.name}`}
                                    onChange={(value) => updateTrueFalseField(index, 'name', value)}
                                    disabled='true'
                                />
                            </td>
                            <td>
                                <TextControl
                                    placeholder="True Label"
                                    value={field.true_label}
                                    onChange={(value) => updateTrueFalseField(index, 'true_label', value)}
                                />
                            </td>
                            <td>
                                <TextControl
                                    placeholder="False Label"
                                    value={field.false_label}
                                    onChange={(value) => updateTrueFalseField(index, 'false_label', value)}
                                />
                            </td>
                            <td>
                                <ToggleControl
                                    label="Show in REST API"
                                    checked={field.show_in_rest}
                                    onChange={(value) => updateTrueFalseCheckbox(index, value)}
                                />
                            </td>
                            <td>
                                <Button
                                    isDestructive
                                    onClick={() => confirmAndRemoveGroup(index, 'truefalse', field.name)}
                                >
                                    Remove
                                </Button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <Button isPrimary onClick={handleAddTrueFalseField}>Add True/False Field</Button>
        </div>
    );
    
    

    return (
        <div className="wrap">
            <h1>Adaptive Content Enhancer Settings</h1>
            {renderChoicesTable()}
            {renderTrueFalseFieldsTable()}
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
