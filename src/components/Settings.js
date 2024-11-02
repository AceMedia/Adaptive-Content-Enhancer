import { useState, useEffect } from '@wordpress/element';
import { ConfirmDialog } from '@wordpress/components';
import { render } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';



function createLowercaseUnderscored(string) {
    return string
        .toLowerCase() // Convert to lowercase
        .replace(/[^a-z0-9_ ]+/g, '') // Remove any character that is not a-z, 0-9, or space
        .replace(/\s+/g, '_'); // Replace spaces with underscores
}

import MainSettingsPanel from './MainSettingsPanel';


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
const choicesPerPage = 99; // Define how many choices to load per page
const [totalPages, setTotalPages] = useState(1); // Track the total number of pages
const [sortBy, setSortBy] = useState('edited'); // Default sort by 'created'
const [fieldTypeFilter, setFieldTypeFilter] = useState(['']); // Default to "All Types"
const [isConfirmDialogOpen, setIsConfirmDialogOpen] = useState(false);
const [choiceToDelete, setChoiceToDelete] = useState(null);
const [unsavedChanges, setUnsavedChanges] = useState(false);
const [newlyAddedChoice, setNewlyAddedChoice] = useState(null); // Track newly added choice
const [groupBy, setGroupBy] = useState('field_type'); // Default grouping by 'type'
const [tagFilter, setTagFilter] = useState(['']); // Default to "All Tags"
const [tags, setTags] = useState([]);
const [searchQuery, setSearchQuery] = useState(''); // State to store search query


const handleSearchChange = (query) => {
    setSearchQuery(query);
};

const choicesWithDates = choices.map((choice) => ({
    ...choice,
    modifiedAt: choice.edited || choice.created, // Fallback if modifiedAt is missing
}));

const handleFieldTypeChange = (selectedType) => {
    setFieldTypeFilter(selectedType);
    setCurrentPage(1); // Reset to first page when filter changes
};

const handleGroupByChange = (groupOption) => {
    setGroupBy(groupOption); // Update the grouping state
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
                comparison = new Date(b.created) - new Date(a.created);
                break;
            case 'edited':
                comparison = new Date(b.edited) - new Date(a.edited);
                break;
            case 'title':
                comparison = a.title.localeCompare(b.title);
                break;
            default:
                comparison = new Date(b.edited) - new Date(a.edited); // Default to edited
        }
        return comparison;
    });
};

useEffect(() => {
    const sortedChoices = sortChoices(choices, sortBy);
    setChoices(sortedChoices);
}, [choices, sortBy]); 


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
        { label: 'True', value: 'true', disabled: true },
        { label: 'False', value: 'false', disabled: true },
    ];
};


const getDefaultOptions = () => {
    return [
        { label: '', value: '', disabled: false },
        { label: '', value: '', disabled: false },
        { label: '', value: '', disabled: false },
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
            // Only set options if the field is 'field_type'
            options: field === 'field_type'
                ? (newValue === 'truefalse' ? getTrueFalseOptions() : getDefaultOptions())
                : prevNewChoice.options, // Keep existing options if the field isn't 'field_type'
        }));
    } else {
        setChoices((prevChoices) => {
            const updatedChoices = [...prevChoices];
            const choiceIndex = updatedChoices.findIndex((c) => c.name === choice.name);

            if (choiceIndex !== -1) {
                if (field === 'name' && !choice.isNew) {
                    return updatedChoices;
                }

                updatedChoices[choiceIndex][field] = formattedValue;

                // Update options only if the field is 'field_type'
                if (field === 'field_type') {
                    updatedChoices[choiceIndex].options = newValue === 'truefalse'
                        ? getTrueFalseOptions()
                        : getDefaultOptions();
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
        }, 10000);
    
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

   
    
  // In Settings.js
// In Settings.js
const fetchChoices = ({ navigateToLastPage = false } = {}) => {
    const fieldTypeQuery = fieldTypeFilter.length === 0 || fieldTypeFilter.includes('') ? '' : fieldTypeFilter.join(',');
    const tagQuery = tagFilter.length === 0 || tagFilter.includes('') ? '' : tagFilter.join(',');
    const searchQueryParam = searchQuery.length >= 3 ? `&search_query=${searchQuery}` : '';

    apiFetch({ path: `/ace/v1/get-choices?page=${navigateToLastPage ? totalPages : currentPage}&per_page=${choicesPerPage}&field_type=${fieldTypeQuery}&tags=${tagQuery}${searchQueryParam}` })
        .then((response) => {
            const { choices: fetchedChoices, totalPages: updatedTotalPages, tags: fetchedTags } = response;

            const updatedChoices = fetchedChoices.map(choice => ({
                ...choice,
                modifiedAt: choice.edited || choice.created,
                options: (choice.options || []).map(option => ({
                    ...option,
                    disabled: true
                }))
            }));

            // Apply sorting before setting state
            setChoices(sortChoices(updatedChoices, sortBy));
            setTotalPages(updatedTotalPages);
            setTags(fetchedTags); // Set the fetched tags
            if (navigateToLastPage) {
                setCurrentPage(updatedTotalPages); // Navigate to the last page
            }
        })
        .catch((error) => console.error('Error fetching choices:', error));
};

// Ensure fetchChoices is called when fieldTypeFilter, tagFilter, or searchQuery changes
useEffect(() => {
    fetchChoices();
}, [fieldTypeFilter, tagFilter, searchQuery, currentPage, sortBy]);
    

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
        const confirmDelete = window.confirm(`Are you sure you want to delete ${groupName}? This action cannot be undone!`);
        if (confirmDelete) {
            handleConfirmDelete(index, groupName);
        }
    };
    
    const handleConfirmDelete = (index, groupName) => {
    
        const processedGroupName = createLowercaseUnderscored(groupName);
        const optionName = 'choices_' + processedGroupName;
    
        apiFetch({
            path: '/ace/v1/delete-option',
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ option_name: optionName, _wpnonce: aceAjax.nonce })
        })
        .then((response) => {
            if (response.success) {
                setChoices((prevChoices) => prevChoices.filter((_, i) => i !== index));
            } else {
                console.error('Failed to remove group:', response.error);
            }
        })
        .catch((error) => console.error('Error removing group:', error));
    };
    
    
    const handleCancelDelete = () => {
        setIsConfirmDialogOpen(false);
        setChoiceToDelete(null);
    };
    

    

    const updateOption = (choice, option, field, value) => {
        if (isModalOpen) {
            setNewChoice((prevNewChoice) => {
                const updatedOptions = prevNewChoice.options.map((opt, index) => {
                    if (opt === option) {
                        // Update the targeted option only
                        return {
                            ...opt,
                            [field]: value,
                            // Sync value with label only if it was not manually modified before
                            value: field === 'label' && opt.value === createLowercaseUnderscored(opt.label)
                                ? createLowercaseUnderscored(value)
                                : field === 'value'
                                    ? createLowercaseUnderscored(value) // Allow manual input
                                    : opt.value,
                        };
                    }
                    return opt;
                });
                return { ...prevNewChoice, options: updatedOptions };
            });
        } else {
            setChoices((prevChoices) => {
                const updatedChoices = [...prevChoices];
                const choiceIndex = updatedChoices.findIndex((c) => c.name === choice.name);
    
                if (choiceIndex !== -1) {
                    const optionIndex = updatedChoices[choiceIndex].options.findIndex(
                        (opt) => opt === option
                    );
    
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
        if (isModalOpen) {
            // Handle removal in the modal for newChoice
            setNewChoice((prevNewChoice) => {
                const updatedOptions = prevNewChoice.options.filter(opt => opt.label !== option.label);
                return {
                    ...prevNewChoice,
                    options: updatedOptions,
                };
            });
        } else {
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
    }
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
    

    const [openCardIds, setOpenCardIds] = useState([]); // Rename to be more specific

    const toggleOpen = (cardId) => {
        setOpenCardIds(prev => {
            const isOpen = prev.includes(cardId);
            return isOpen ? prev.filter(id => id !== cardId) : [cardId]; // Only keep clicked card ID
        });
    };
    

    // Automatically open the new choice card and highlight it
    useEffect(() => {
        if (newlyAddedChoice) {
            const newChoice = choices.find(choice => choice.name === newlyAddedChoice);
            if (newChoice?.id) {
                setOpenCardIds(prev => [...prev, newChoice.id]);
    
                setTimeout(() => {
                    setNewlyAddedChoice(null);
                }, 3000);
            }
        }
    }, [newlyAddedChoice, choices]);


    return (
        <>
            <MainSettingsPanel
                choices={choices} // Pass choices instead of trying to group here
                groupBy={groupBy}
                sortBy={sortBy}
                handleGroupByChange={handleGroupByChange}
                newlyAddedChoice={newlyAddedChoice}
                openIndexes={openCardIds}
                toggleOpen={toggleOpen}
                tagFilter={tagFilter}
                setTagFilter={setTagFilter}
                tags={tags}
                setFieldTypeFilter={setFieldTypeFilter} // Pass the setter function
                handleTextChange={handleTextChange}
                handleToggleChange={handleToggleChange}
                handleTagChange={handleTagChange}
                updateOption={updateOption}
                removeOption={removeOption}
                addOption={addOption}
                handleSortChange={handleSortChange}
                handleFieldTypeChange={handleFieldTypeChange}
                fieldTypeFilter={fieldTypeFilter}
                currentPage={currentPage}
                totalPages={totalPages}
                handlePreviousPage={handlePreviousPage}
                handleNextPage={handleNextPage}
                handleSave={handleSave}
                setUnsavedChanges={setUnsavedChanges}
                unsavedChanges={unsavedChanges}
                handleAddChoiceGroup={handleAddChoiceGroup}
                isModalOpen={isModalOpen}
                setIsModalOpen={setIsModalOpen}
                newChoice={newChoice}
                handleModalSubmit={handleModalSubmit}
                openConfirmDialog={openConfirmDialog}
                formatRelativeTime={formatRelativeTime}
                searchQuery={searchQuery}
                handleSearchChange={handleSearchChange} 
            />
    
    {isConfirmDialogOpen && choiceToDelete && (
                <ConfirmDialog
                    isOpen={isConfirmDialogOpen}
                    onConfirm={handleConfirmDelete}
                    onCancel={handleCancelDelete}
                    confirmButtonText="Yes, Delete"
                    cancelButtonText="Cancel"
                >
                    Are you sure you want to delete <strong>{choiceToDelete.groupName}</strong>? This action cannot be undone!
                </ConfirmDialog>
            )}
        </>
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
