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
    DropdownMenu,
    MenuGroup,
    MenuItem,
    TagInput,
    Tooltip,
    Icon,
    Flex, FlexItem, FlexBlock,
    __experimentalConfirmDialog as ConfirmDialog
} from '@wordpress/components';
import { date } from '@wordpress/icons'; // You may need to add icons for other purposes

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
const [newChoice, setNewChoice] = useState({ 
    title: '', 
    name: '', 
    field_type: 'text', 
    description: '', 
    options: [], 
    tags: [] // Add tags property
});

const [changedChoices, setChangedChoices] = useState([]);
const [currentPage, setCurrentPage] = useState(1); // Track the current page of choices
const [hasMoreChoices, setHasMoreChoices] = useState(true); // Track if there are more choices to load
const choicesPerPage = 10; // Define how many choices to load per page
const [totalPages, setTotalPages] = useState(1); // Track the total number of pages
const [sortBy, setSortBy] = useState('created'); // Default sort by 'created'
const [fieldTypeFilter, setFieldTypeFilter] = useState(''); // Default to no filter
const [isConfirmDialogOpen, setIsConfirmDialogOpen] = useState(false);
const [choiceToDelete, setChoiceToDelete] = useState(null);
const [unsavedChanges, setUnsavedChanges] = useState(false);
const [newlyAddedChoice, setNewlyAddedChoice] = useState(null); // Track newly added choice


const handleFieldTypeChange = (selectedType) => {
    setFieldTypeFilter(selectedType);
    setCurrentPage(1); // Reset to first page when filter changes
};


const handleSortChange = (sortMethod) => {
    setSortBy(sortMethod);
};

// Apply sorting to the fetched choices
const sortChoices = (choices, sortBy) => {
    return choices.sort((a, b) => {
        let comparison = 0;
        switch (sortBy) {
            case 'created':
                comparison = new Date(a.created) - new Date(b.created);
                break;
            case 'edited':
                comparison = new Date(a.edited) - new Date(b.edited);
                break;
            case 'title':
                comparison = a.title.localeCompare(b.title);
                break;
            case 'field_type':
                comparison = a.field_type.localeCompare(b.field_type);
                break;
            default:
                break;
        }
        // Use created date as a secondary sort to ensure stability
        if (comparison === 0) {
            comparison = new Date(a.created) - new Date(b.created);
        }
        return comparison;
    });
};


const handleTagChange = (choice, newTags) => {
    if (isModalOpen) {
        setNewChoice((prevNewChoice) => ({
            ...prevNewChoice,
            tags: newTags,
        }));
    } else {
        setChoices((prevChoices) => {
            const updatedChoices = [...prevChoices];
            const choiceIndex = updatedChoices.findIndex(
                (c) => c.name === choice.name
            );

            if (choiceIndex !== -1) {
                updatedChoices[choiceIndex].tags = newTags;
                markAsChanged(choice.name); // Mark as changed
            }

            return updatedChoices;
        });
    }
};

const markAsChanged = (choiceName) => {
    setChangedChoices((prevChanged) => {
        if (!prevChanged.includes(choiceName)) {
            setUnsavedChanges(true); // Correctly indicate there are unsaved changes
            return [...prevChanged, choiceName];
        }
        return prevChanged;
    });
};


const getTrueFalseOptions = () => {
    return [
        { label: 'True', value: '1', disabled: true },
        { label: 'False', value: '0', disabled: true },
    ];
};


const handleTextChange = (choice, field, newValue) => {
    const formattedValue = field === 'name' ? createLowercaseUnderscored(newValue) : newValue;

    if (isModalOpen) {
        setNewChoice((prevNewChoice) => ({
            ...prevNewChoice,
            [field]: formattedValue,
            // Only sync 'name' when updating 'title' and name hasn't been manually modified
            name: field === 'title' && prevNewChoice.name === createLowercaseUnderscored(prevNewChoice.title)
                ? createLowercaseUnderscored(newValue)
                : prevNewChoice.name,
            options: field === 'field_type' && newValue === 'truefalse'
                ? getTrueFalseOptions()
                : prevNewChoice.options,
        }));
    } else {
        setChoices((prevChoices) => {
            const updatedChoices = [...prevChoices];
            const choiceIndex = updatedChoices.findIndex(
                (c) => c.name === choice.name
            );

            if (choiceIndex !== -1) {
                if (field === 'name' && !choice.isNew) {
                    return updatedChoices;
                }

                updatedChoices[choiceIndex][field] = formattedValue;

                if (field === 'field_type' && newValue === 'truefalse') {
                    updatedChoices[choiceIndex].options = getTrueFalseOptions();
                }

                markAsChanged(choice.name);
            }

            return updatedChoices;
        });
    }
};





const handleSave = () => {
    const payload = {
        choices: choices
            .filter(choice => changedChoices.includes(choice.name))
            .map(choice => ({
                name: choice.name,
                title: choice.title,
                field_type: choice.field_type,
                description: choice.description,
                options: choice.options ? choice.options.map(option => ({
                    label: option.label,
                    value: option.value, // Only set disabled once saved, not before
                })) : [],
                show_in_rest: choice.show_in_rest ? 1 : 0,
                tags: choice.tags,
                created: choice.created,
                edited: new Date().toISOString(),
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
            const updatedChoiceNames = payload.choices.map(choice => choice.name);
            // Re-fetch and ensure options are disabled only after successful save
            refreshUpdatedChoices(updatedChoiceNames);

            // Refresh the current page to reflect updates without changing position
            fetchChoices({ currentPage });

            setChangedChoices([]); // Reset changed choices
            return { success: true };
        } else {
            console.error('Error:', response.error);
            return { success: false };
        }
    }).catch((error) => {
        console.error('Error saving settings:', error);
        return { success: false };
    });
};



    
    
    
    
const refreshUpdatedChoices = (updatedChoiceNames) => {
    apiFetch({ path: `/ace/v1/get-choices?names=${updatedChoiceNames.join(',')}` })
        .then((response) => {
            const { choices: updatedChoices } = response;

            // Ensure all options are marked as disabled after successful save
            const updatedChoicesWithDisabledOptions = updatedChoices.map(choice => {
                const updatedOptions = (choice.options || []).map(option => ({
                    ...option,
                    disabled: true // Disable all options after being saved
                }));

                return {
                    ...choice,
                    options: updatedOptions,
                };
            });

            // Merge updated choices into the current list without changing order
            setChoices((prevChoices) => {
                const updatedChoiceMap = Object.fromEntries(updatedChoicesWithDisabledOptions.map(choice => [choice.name, choice]));
                return prevChoices.map((choice) => 
                    updatedChoiceMap[choice.name] ? updatedChoiceMap[choice.name] : choice
                );
            });
        })
        .catch((error) => console.error('Error refreshing updated choices:', error));
};



    useEffect(() => {
        const interval = setInterval(() => {
            // Force a state update to refresh the relative times
            setChoices((prevChoices) => [...prevChoices]);
        }, 1000);
    
        // Cleanup interval on component unmount
        return () => clearInterval(interval);
    }, []);

    useEffect(() => {
        apiFetch({ path: `/ace/v1/get-choices?page=${currentPage}&per_page=${choicesPerPage}&field_type=${fieldTypeFilter}` })
            .then((response) => {
                const { choices: fetchedChoices, totalPages } = response;
                
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
                        options: updatedOptions,
                        created: choice.created, // Store created timestamp
                        edited: choice.edited // Store edited timestamp
                    };
                });
    
                // Apply sorting before setting state
                setChoices(sortChoices(updatedChoices, sortBy));
                setTotalPages(totalPages);
    
                if (currentPage >= totalPages) {
                    setHasMoreChoices(false);
                }
            })
            .catch((error) => console.error('Error fetching choices:', error));
    }, [currentPage, sortBy, fieldTypeFilter]); // Include `fieldTypeFilter` as a dependency
      
    
    const loadMoreChoices = () => {
        setCurrentPage(prevPage => prevPage + 1);
    };



    const handlePreviousPage = () => {
        if (currentPage > 1) {
            setCurrentPage(currentPage - 1);
        }
    };
    
    const handleNextPage = () => {
        if (currentPage < totalPages) {
            setCurrentPage(currentPage + 1);
        }
    };
    
    

    const [inputValue, setInputValue] = useState('');

   
    


    const fetchChoices = ({ navigateToLastPage = false } = {}) => {
        apiFetch({ path: `/ace/v1/get-choices?page=${navigateToLastPage ? totalPages : currentPage}&per_page=${choicesPerPage}&field_type=${fieldTypeFilter}` })
            .then((response) => {
                const { choices: fetchedChoices, totalPages: updatedTotalPages } = response;
    
                const updatedChoices = fetchedChoices.map(choice => {
                    if (choice.field_type === 'truefalse') {
                        return {
                            ...choice,
                            true_label: choice.options?.find(option => option.value === '1')?.label || 'True',
                            false_label: choice.options?.find(option => option.value === '0')?.label || 'False'
                        };
                    }
    
                    // Ensure all options are marked as disabled when fetched
                    const updatedOptions = (choice.options || []).map(option => ({
                        ...option,
                        disabled: true // Disable all options
                    }));
    
                    return {
                        ...choice,
                        options: updatedOptions,
                        created: choice.created, // Store created timestamp
                        edited: choice.edited // Store edited timestamp
                    };
                });
    
                setChoices(sortChoices(updatedChoices, sortBy));
                setTotalPages(updatedTotalPages);
                if (navigateToLastPage) {
                    setCurrentPage(updatedTotalPages); // Navigate to the last page
                }
    
                if (currentPage >= updatedTotalPages) {
                    setHasMoreChoices(false);
                }
            })
            .catch((error) => console.error('Error fetching choices:', error));
    };
    
    
    
    
    // Use fetchChoices in useEffect
    useEffect(() => {
        fetchChoices();
    }, [currentPage, sortBy, fieldTypeFilter]);
    
    

    const handleAddChoiceGroup = () => {
        setIsModalOpen(true);
    };

    const handleModalSubmit = () => {
        const newTitle = newChoice.title || `Group ${choices.length + 1}`;
        const newChoiceData = {
            name: newChoice.name,
            title: newTitle,
            field_type: newChoice.field_type,
            description: newChoice.description,
            options: newChoice.options,
            tags: newChoice.tags, // Correctly pass tags
            show_in_rest: newChoice.show_in_rest, // Use the value from newChoice
            isNew: true,
            created: new Date().toISOString(),
            edited: new Date().toISOString()
        };
    
        apiFetch({
            path: '/ace/v1/save-options',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                choices: [newChoiceData],
                _wpnonce: aceAjax.nonce,
            }),
        }).then((response) => {
            if (response.success) {
                setIsModalOpen(false);
                resetNewChoice();
                setNewlyAddedChoice(newChoiceData.name); // Store the name of the new choice
                fetchChoices({ navigateToLastPage: true });
            } else {
                console.error('Error saving new group:', response.error);
                alert('Failed to save the new group. Please try again.');
            }
        }).catch((error) => {
            console.error('Error saving new group:', error);
            alert('An error occurred. Please try again.');
        });
    };
    
    
    
    
    
    

    const resetNewChoice = () => {
        setNewChoice({ 
            title: '', 
            name: '', 
            field_type: 'text', 
            description: '', 
            options: [], 
            tags: [], // Reset tags
            show_in_rest: false // Reset show_in_rest
        });
    };
    

    const openConfirmDialog = (index, groupName) => {
        setChoiceToDelete({ index, groupName });
        setIsConfirmDialogOpen(true);
    };
    
    const handleConfirmDelete = () => {
        const { index, groupName } = choiceToDelete;
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
    
        setIsConfirmDialogOpen(false);
        setChoiceToDelete(null);
    };
    
    const handleCancelDelete = () => {
        setIsConfirmDialogOpen(false);
        setChoiceToDelete(null);
    };
    
    const updateOption = (choice, option, field, value) => {
        if (isModalOpen) {
            setNewChoice((prevNewChoice) => {
                const updatedOptions = prevNewChoice.options.map((opt) =>
                    opt.label === option.label ? {
                        ...opt,
                        [field]: value,
                        // Prevent updates to the `value` field if it's a `truefalse` type
                        value: prevNewChoice.field_type === 'truefalse' ? opt.value : (field === 'label' && !opt.disabled ? createLowercaseUnderscored(value) : opt.value),
                    } : opt
                );
                return { ...prevNewChoice, options: updatedOptions };
            });
        } else {
            setChoices((prevChoices) => {
                const updatedChoices = [...prevChoices];
                const choiceIndex = updatedChoices.findIndex((c) => c.name === choice.name);
    
                if (choiceIndex !== -1) {
                    const optionIndex = updatedChoices[choiceIndex].options.findIndex((opt) => opt.label === option.label);
    
                    if (optionIndex !== -1) {
                        const updatedOption = { ...updatedChoices[choiceIndex].options[optionIndex] };
    
                        // Only allow label updates, and never update `value` for `truefalse`
                        if (field === 'label') {
                            updatedOption.label = value;
                            if (!updatedOption.disabled && choice.field_type !== 'truefalse') {
                                updatedOption.value = createLowercaseUnderscored(value);
                            }
                        } else if (field === 'value' && choice.field_type !== 'truefalse') {
                            // Only update the value if it's not a `truefalse` type
                            updatedOption.value = createLowercaseUnderscored(value);
                        }
    
                        updatedChoices[choiceIndex].options[optionIndex] = updatedOption;
                        markAsChanged(choice.name); // Mark as changed
                    }
                }
                return updatedChoices;
            });
        }
    };
    
    
    


    const handleToggleChange = (choice, newValue) => {
        if (isModalOpen) {
            setNewChoice((prevNewChoice) => ({
                ...prevNewChoice,
                show_in_rest: newValue,
            }));
        } else {
            setChoices((prevChoices) => {
                const updatedChoices = [...prevChoices];
                const choiceIndex = updatedChoices.findIndex(
                    (c) => c.name === choice.name
                );
    
                if (choiceIndex !== -1) {
                    updatedChoices[choiceIndex].show_in_rest = newValue;
                    markAsChanged(choice.name); // Mark as changed
                }
    
                return updatedChoices;
            });
        }
    };
    

    const addOption = (choice) => {
        const newOption = { label: '', value: '', isNew: true, disabled: false }; // Default to not disabled
    
        if (choice) {
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
        } else if (isModalOpen) {
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
                const updatedOptions = c.options.filter(opt => opt.label !== option.label);
                
                // Mark the choice as changed
                markAsChanged(c.name);
                
                return {
                    ...c,
                    options: updatedOptions
                };
            }
            return c;
        });
        setChoices(updatedChoices);
    };

    const formatRelativeTime = (timestamp) => {
        if (!timestamp) return null;
    
        const time = new Date(timestamp);
        const now = new Date();
        const diffInSeconds = Math.floor((now - time) / 1000);
    
        const intervals = {
            year: 31536000,  // 365 days
            month: 2592000,  // 30 days
            week: 604800,    // 7 days
            day: 86400,      // 24 hours
            hour: 3600,      // 60 minutes
            minute: 60,      // 60 seconds
            second: 1,
        };
    
        for (const [key, seconds] of Object.entries(intervals)) {
            const interval = Math.floor(diffInSeconds / seconds);
            if (interval >= 1) {
                return `${interval} ${key}${interval !== 1 ? 's' : ''} ago`;
            }
        }
    
        return 'Just now';
    };
    

    const renderChoiceFields = (choice) => (
        <>
            
            <TextControl
            label="Group Title"
            value={choice.title}
            onChange={(newValue) => handleTextChange(choice, 'title', newValue)}
        />

<TextControl
    label="Group Name"
    value={choice.name}
    disabled={!isModalOpen && !choice.isNew} // Only disable when not in modal and not a new group
    onChange={(newValue) => {
        const formattedValue = createLowercaseUnderscored(newValue);
        handleTextChange(choice, 'name', formattedValue);
        setNewChoice((prevNewChoice) => ({
            ...prevNewChoice,
            name: formattedValue,
        }));
    }} // Allow manual updates and apply formatting
/>

            <SelectControl
                label="Field Type"
                value={choice.field_type}
                options={[
                    { label: 'Text', value: 'text' },
                    { label: 'Select', value: 'select' },
                    { label: 'Checkbox', value: 'checkbox' },
                    { label: 'True/False', value: 'truefalse' },
                    { label: 'Radio', value: 'radio' },
                    { label: 'Buttons', value: 'buttons' },
                ]}
                onChange={(newValue) => handleTextChange(choice, 'field_type', newValue)} // Update choice's field_type directly
            />
            <TextareaControl
                label="Description"
                value={choice.description}
                onChange={(newValue) => handleTextChange(choice, 'description', newValue)}
            />
            <ToggleControl
    label="Show in REST API"
    checked={choice.show_in_rest}
    onChange={(newValue) => handleToggleChange(choice, newValue)}
/>


        <TextControl
            label="Tags (comma-separated)"
            value={choice.tags ? choice.tags.join(', ') : ''}
            onChange={(newValue) => handleTagChange(choice, newValue.split(',').map(tag => tag.trim()))}
        />




        {/* Display tags and descriptions */}
        {choice.tags_with_descriptions && choice.tags_with_descriptions.length > 0 && (
            <div style={{ marginTop: '16px' }}>
                <strong>Tags with Descriptions:</strong>
                <ul>
                    {choice.tags_with_descriptions.map((tag, index) => (
                        <li key={index}>
                            <strong>{tag.name}:</strong> {tag.description}
                        </li>
                    ))}
                </ul>
            </div>
        )}

{choice.field_type !== 'text' && (
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
    disabled={choice.field_type === 'truefalse' || !!option.disabled} // Ensure the option is disabled if marked or for true/false
        onChange={(value) => updateOption(choice, option, 'value', value)}
/>

                    <Button
                        isDestructive
                        onClick={() => removeOption(choice, option)}
                        disabled={choice.field_type === 'truefalse'} // Prevent removal of true/false options
                    >
                        Remove
                    </Button>
                </div>
            ))}
            {choice.field_type !== 'truefalse' && (
                <Button 
                    isPrimary 
                    onClick={() => {
                        const option = isModalOpen ? null : choice;
                        addOption(option);
                    }}
                >
                    Add Option
                </Button>
            )}
        </div>

)}


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
    
        // Automatically open the new choice card and highlight it
        useEffect(() => {
            if (newlyAddedChoice) {
                const newIndex = choices.findIndex((choice) => choice.name === newlyAddedChoice);
                if (newIndex !== -1) {
                    setOpenIndexes((prev) => ({
                        ...prev,
                        [newIndex]: true, // Open the card
                    }));
    
                    // Remove the highlight after 3 seconds
                    setTimeout(() => {
                        setNewlyAddedChoice(null);
                    }, 3000);
                }
            }
        }, [newlyAddedChoice, choices]);
    
    
        return (
            <div>
                <h2>Option Groups</h2>
                {choices.map((choice, index) => (
                    <Card 
                    key={index} 
                    style={{ 
                        width: '100%', 
                        marginBottom: '20px', 
                        border: newlyAddedChoice === choice.name ? '2px solid #007cba' : 'none', // Add highlight
                        transition: 'border 0.3s' // Smooth transition
                    }}
                    className={newlyAddedChoice === choice.name ? 'highlight' : ''} // Add highlight class
                >
                    <CardHeader
                        onClick={() => toggleOpen(index)}
                        style={{
                            cursor: 'pointer',
                            padding: '10px',
                            backgroundColor: '#f5f5f5',
                            borderBottom: '1px solid #ddd',
                            borderRadius: '4px'
                        }}
                    >
    <Flex justify="space-between" align="center" gap={2}>
        {/* Left-aligned group name */}
        <FlexItem>
            <Flex align="center" gap={2} style={{ textAlign: 'left' }}>
                <Icon icon={
                    choice.field_type === 'text' ? 'menu' :
                    choice.field_type === 'select' ? 'menu' :
                    choice.field_type === 'checkbox' ? 'yes-alt' :
                    choice.field_type === 'truefalse' ? 'admin-site-alt3' :
                    choice.field_type === 'radio' ? 'radio' :
                    'admin-tools'
                } />
                <strong>{choice.title || `Group ${index + 1}`}</strong>
            </Flex>
        </FlexItem>

        {/* Right-aligned metadata and tags */}
        <Flex align="center" gap={3} style={{ textAlign: 'right', width: 'auto' }}>
            {/* Metadata for created/edited times */}
            {choice.created && (
                <div style={{ fontSize: '0.85em', color: '#777', display: 'flex', gap: '10px' }}>
                    <Tooltip text={`Created at ${new Date(choice.created).toLocaleString()}`}>
                        <p><strong>Created:</strong> {formatRelativeTime(choice.created)}</p>
                    </Tooltip>
                    <Tooltip text={`Last edited at ${new Date(choice.edited).toLocaleString()}`}>
                        <p><strong>Last Edited:</strong> {formatRelativeTime(choice.edited)}</p>
                    </Tooltip>
                </div>
            )}

            {/* Display tags as styled spans */}
            {choice.tags && choice.tags.length > 0 && (
                <Flex align="left" wrap="nowrap" gap={1} style={{ textAlign: 'left', width: 'auto' }}>
                    {choice.tags.map((tag, idx) => (
                        <span 
                            key={idx} 
                            style={{
                                backgroundColor: '#007cba',
                                color: '#fff',
                                padding: '2px 6px',
                                borderRadius: '12px',
                                fontSize: '0.75em',
                                whiteSpace: 'nowrap'
                            }}
                        >
                            {tag}
                        </span>
                    ))}
                </Flex>
            )}
        </Flex>
    </Flex>
</CardHeader>

                        {openIndexes[index] && (
                            <CardBody>
                                {renderChoiceFields(choice)}
                                <Button 
                                    isDestructive 
                                    onClick={() => openConfirmDialog(index, choice.name)}
                                >
                                    Delete Group
                                </Button>
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

            <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            <DropdownMenu
                icon="sort"
                label="Sort Options"
                toggleProps={{ isPrimary: true }}
            >
                {({ onClose }) => (
                    <MenuGroup label="Sort by">
                        <MenuItem onClick={() => { handleSortChange('created'); onClose(); }}>Date Created</MenuItem>
                        <MenuItem onClick={() => { handleSortChange('edited'); onClose(); }}>Date Edited</MenuItem>
                        <MenuItem onClick={() => { handleSortChange('title'); onClose(); }}>Alphabetical</MenuItem>
                    </MenuGroup>
                )}
            </DropdownMenu>

            <SelectControl
                label="Filter by Field Type"
                value={fieldTypeFilter}
                options={[
                    { label: 'All Types', value: '' },
                    { label: 'Text', value: 'text' },
                    { label: 'Select', value: 'select' },
                    { label: 'Checkbox', value: 'checkbox' },
                    { label: 'True/False', value: 'truefalse' },
                    { label: 'Radio', value: 'radio' },
                    { label: 'Buttons', value: 'buttons' }
                ]}
                onChange={(newValue) => handleFieldTypeChange(newValue)}
            />
        </div>

            {renderChoicesTable()}

            {currentPage > 1 && (
    <Button isSecondary onClick={handlePreviousPage}>
        Previous
    </Button>
)}

{currentPage < totalPages && (
    <Button isSecondary onClick={handleNextPage}>
        Next
    </Button>
)}


<SaveBar 
    onSave={handleSave} 
    setUnsavedChanges={setUnsavedChanges} 
    unsavedChanges={unsavedChanges} // Pass unsavedChanges as a prop
>
                <Button isPrimary variant="secondary" onClick={handleAddChoiceGroup}>Add Options</Button>
            </SaveBar>

            {/* Modal for adding new choice group */}
            {isModalOpen && (
                <Modal
                    title="Add New Choice Group"
                    onRequestClose={() => setIsModalOpen(false)}
                >
                    {renderChoiceFields(newChoice)} {/* Render the new choice fields */}
                    <Button isPrimary onClick={handleModalSubmit}>Add Group</Button>
                </Modal>
            )}

{isConfirmDialogOpen && (
    <ConfirmDialog
        isOpen={isConfirmDialogOpen}
        onConfirm={handleConfirmDelete}
        onCancel={handleCancelDelete}
        confirmButtonText="Yes, Delete"
        cancelButtonText="Cancel"
    >
        Are you sure? <strong>This action cannot be undone!</strong>
    </ConfirmDialog>
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
