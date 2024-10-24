import { useState, useEffect } from '@wordpress/element';
import { render } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { 
    TextControl, 
    SelectControl, 
    TextareaControl, 
    ToggleControl, 
    Button, 
    Card, 
    CardHeader,
    CardBody, 
    Modal,
} from '@wordpress/components';

import SaveBar from './SaveBar/SaveBar'; // Adjust the path as needed

function createLowercaseUnderscored(string) {
    return string
        .toLowerCase() // Convert to lowercase
        .replace(/[^a-z0-9_ ]+/g, '') // Remove any character that is not a-z, 0-9, or space
        .replace(/\s+/g, '_'); // Replace spaces with underscores
}

const AdaptiveContentEnhancer = () => {
    const [choices, setChoices] = useState([]);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [newChoice, setNewChoice] = useState({ title: '', name: '', field_type: 'select', description: '', options: [] });

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

                    // Ensure existing options are marked as disabled
                    const updatedOptions = (choice.options || []).map(option => ({
                        ...option,
                        disabled: !!option.value // Disable existing options that have a value
                    }));

                    return {
                        ...choice,
                        options: updatedOptions
                    };
                });

                setChoices(updatedChoices);
            })
            .catch((error) => console.error('Error fetching choices:', error));
    }, []);

    const handleSave = () => {
        const payload = {
            choices: choices.map(choice => ({
                name: choice.name,
                title: choice.title,
                field_type: choice.field_type,
                description: choice.description,
                options: choice.options ? choice.options.map(option => ({
                    label: option.label,
                    value: option.value,
                })) : [],
                show_in_rest: choice.show_in_rest ? 1 : 0
            })),
            _wpnonce: aceAjax.nonce,
        };

        return apiFetch({
            path: '/ace/v1/save-options',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        }).then((response) => {
            if (response.success) {
                setChoices(choices.map(choice => ({ ...choice, isNew: false })));
                return { success: true }; // Resolve promise with success
            } else {
                console.error('Error:', response.error);
                return { success: false }; // Resolve with failure
            }
        }).catch((error) => {
            console.error('Error saving settings:', error);
            return { success: false }; // Resolve with failure
        });
    };

    const handleAddChoiceGroup = () => {
        setIsModalOpen(true);
    };

    const handleModalSubmit = () => {
        const newTitle = newChoice.title || `Group ${choices.length + 1}`;
        const newChoiceData = {
            name: createLowercaseUnderscored(newTitle), // Create hidden key
            title: newTitle, // Set default title
            field_type: newChoice.field_type,
            description: newChoice.description,
            options: newChoice.options,
            show_in_rest: false,
            isNew: true, // Add isNew property to identify this as a new group
        };
        setChoices([...choices, newChoiceData]);
        setIsModalOpen(false);
        resetNewChoice(); // Reset the form
    };

    const resetNewChoice = () => {
        setNewChoice({ title: '', name: '', field_type: 'select', description: '', options: [] }); // Reset form
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
    
    const updateOption = (choice, option, field, value) => {
        setChoices(prevChoices => {
            const updatedChoices = [...prevChoices]; // Create a shallow copy of the previous state
    
            // Find the index of the chosen group
            const choiceIndex = updatedChoices.findIndex(c => c.name === choice.name);
            
            if (choiceIndex !== -1) {
                // Find the index of the specific option
                const optionIndex = updatedChoices[choiceIndex].options.findIndex(opt => opt.label === option.label);
    
                if (optionIndex !== -1) {
                    // Update only the specific option
                    const updatedOption = { ...updatedChoices[choiceIndex].options[optionIndex] };
    
                    if (field === 'label') {
                        updatedOption.label = value; // Update label directly
                        if (!updatedOption.disabled) {
                        updatedOption.value = createLowercaseUnderscored(value); // Sync value with underscored version
                        }
                    } else if (field === 'value') {
                        updatedOption.value = createLowercaseUnderscored(value); // Update value directly when the field is 'value'
                    }
    
                    // Set the updated option in the choices
                    updatedChoices[choiceIndex].options[optionIndex] = updatedOption;
                }
            }
    
            return updatedChoices; // Return the updated choices
        });
    };
    
    
    

    const addOption = (choice) => {
        const newOption = { label: '', value: '' };
    
        if (choice) {
            // If choice is provided, add to the specific choice in the main choices
            const updatedChoices = choices.map(c => {
                if (c.name === choice.name) {
                    return {
                        ...c,
                        options: [...c.options, newOption]
                    };
                }
                return c;
            });
            setChoices(updatedChoices);
        } else {
            // If choice is null, add to newChoice for the modal
            setNewChoice(prev => ({
                ...prev,
                options: [...prev.options, newOption]
            }));
        }
    };
    
    
    const removeOption = (choice, option) => {
        const updatedChoices = choices.map(c => {
            if (c.name === choice.name) {
                // Filter out the option to be removed
                return {
                    ...c,
                    options: c.options.filter(opt => opt.label !== option.label) // Adjust this line based on how you uniquely identify options
                };
            }
            return c;
        });
        setChoices(updatedChoices);
    };
    

    const addOptionToNewChoice = () => {
        setNewChoice(prev => ({
            ...prev,
            options: [...prev.options, { label: '', value: '' }]
        }));
    };

    const removeOptionFromNewChoice = (index) => {
        setNewChoice(prev => ({
            ...prev,
            options: prev.options.filter((_, i) => i !== index)
        }));
    };

    const renderChoiceFields = (choice) => (
        <>
            {/* Main choice fields */}
            <TextControl
                label="Group Title"
                value={choice.title}
            />
            <TextControl
                label="Group Name"
                value={choice.name}
                onChange={(value) => updateChoice(choice, 'name', value)}
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
                onChange={(value) => updateChoice(choice, 'field_type', value)}
            />
            <TextareaControl
                label="Description"
                value={choice.description}
                onChange={(value) => updateChoice(choice, 'description', value)}
            />
            <ToggleControl
                label="Show in REST API"
                checked={choice.show_in_rest}
                onChange={(value) => updateChoice(choice, 'show_in_rest', value)}
            />
    
            <div style={{ marginTop: '16px' }}>
                <strong>Options:</strong>
                {choice.options.map((option, optionIndex) => (
                    <div key={optionIndex} style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <TextControl
    placeholder="Option Label"
    value={option.label}
    onChange={(value) => updateOption(choice, option, 'label', value)}
/>
<TextControl
    placeholder="Option Value"
    value={option.value}
    disabled={option.disabled}
    onChange={(value) => updateOption(choice, option, 'value', value)} // Only updates the value
/>

                        <Button
                            isDestructive
                            onClick={() => removeOption(choice, option)} // Pass the entire choice and option
                        >
                            Remove
                        </Button>
                    </div>
                ))}
                {choice.field_type !== 'truefalse' && (
                    <Button isPrimary onClick={() => addOption(choice)}>Add Option</Button>
                )}
            </div>
        </>
    );
    

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
                                {renderChoiceFields(choice)}
                            </CardBody>
                        )}
                    </Card>
                ))}
            </div>
        );
    };

    return (
        <div className="wrap">
            <h1>Adaptive Content Enhancer Settings</h1>
            {renderChoicesTable()}

            <SaveBar onSave={handleSave}>
                <Button isPrimary variant="secondary" onClick={handleAddChoiceGroup}>Add Options</Button>
            </SaveBar>

            {/* Modal for adding new choice group */}
            {isModalOpen && (
                <Modal
                    title="Add New Choice Group"
                    onRequestClose={() => setIsModalOpen(false)}
                >
                    {renderChoiceFields(newChoice)} {/* Render the new choice fields */}
                    <Button isPrimary onClick={() => addOption(null, true)}>Add Option</Button> {/* Add option to modal */}
                    <Button isPrimary onClick={handleModalSubmit}>Add Group</Button>
                </Modal>
            )}
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
