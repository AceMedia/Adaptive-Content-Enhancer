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
const [groupBy, setGroupBy] = useState('nothing'); // Default grouping by 'type'
const [tagFilter, setTagFilter] = useState(['']); // Default to "All Tags"
const [tags, setTags] = useState([]);
const [searchQuery, setSearchQuery] = useState(''); // State to store search query
const [message, setMessage] = useState(''); // Define message state
const [currentTag, setCurrentTag] = useState(null); // State for the current tag being edited
const [isEditingTag, setIsEditingTag] = useState(false); // State to determine if we are editing a tag
const [elapsedTime, setElapsedTime] = useState(0); // Define elapsedTime state
const [buttonText, setButtonText] = useState('Save Settings'); // Default button text
const [intervalId, setIntervalId] = useState(null); // Define intervalId state
const [itemsToDelete, setItemsToDelete] = useState({ choices: [], tags: [] });
const saveText = "Save Settings"; // Define saveText
const [isAutoSaveEnabled, setIsAutoSaveEnabled] = useState(false); // Track auto-save setting
const [totalFields, setTotalFields] = useState(0);

const [quickEdit, setQuickEdit] = useState('false'); // Initialize quickEdit state


const [isBulkDeleteConfirmDialogOpen, setIsBulkDeleteConfirmDialogOpen] = useState(false);
const [bulkDeleteConfirmMessage, setBulkDeleteConfirmMessage] = useState('');

const throttle = (func, delay) => {
    let timeoutId;
    return (...args) => {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
        timeoutId = setTimeout(() => {
            func(...args);
        }, delay);
    };
};

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


const getRandomColor = () => {
    const letters = '345678'; // Use values that are in the mid-range for richer colors
    let color = '#';
    for (let i = 0; i < 6; i++) {
        color += letters[Math.floor(Math.random() * letters.length)];
    }
    return color;
};


const customStyles = {
    multiValue: (styles, { data }) => ({
        ...styles,
        backgroundColor: data.meta?.color || data.color || '#007cba', // Fallback to default if color is missing
    }),
    multiValueLabel: (styles) => ({
        ...styles,
        color: '#fff', // Text color for contrast
    }),
    multiValueRemove: (styles) => ({
        ...styles,
        color: '#fff',
        ':hover': {
            backgroundColor: '#ff6961', // Slightly darker color on hover for remove button
            color: '#fff',
        },
    }),
};

const handleTagChange = (choice, newTagIds) => {
    if (isModalOpen) {
        setNewChoice((prevNewChoice) => ({
            ...prevNewChoice,
            tags: newTagIds,
        }));
    } else {
        setChoices((prevChoices) => {
            console.log('Setting tags:', newTagIds);
            const updatedChoices = [...prevChoices];
            const choiceIndex = updatedChoices.findIndex(
                (c) => c.name === choice.name
            );

            if (choiceIndex !== -1) {
                updatedChoices[choiceIndex].tags = newTagIds;
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



const [selectedChoices, setSelectedChoices] = useState([]);
const [selectedTags, setSelectedTags] = useState([])


const handleBulkDelete = () => {
    const confirmDelete = window.confirm(`Are you sure you want to delete these items? This action cannot be undone!`);
    if (confirmDelete) {
        setItemsToDelete({ choices: selectedChoices, tags: selectedTags });
    }
};


useEffect(() => {
    if (itemsToDelete.choices.length > 0 || itemsToDelete.tags.length > 0) {
        confirmBulkDelete();
    }
}, [itemsToDelete]);

const confirmBulkDelete = () => {
    
    // Delete selected choices
    const deleteChoicePromises = itemsToDelete.choices.map(choice => {
        if (!choice.name) {
            console.error('Choice does not have a name:', choice);
            return Promise.resolve(); // Skip this choice
        }

        const processedGroupName = createLowercaseUnderscored(choice.name);
        const optionName = 'choices_' + processedGroupName;


        return apiFetch({
            path: '/ace/v1/delete-option',
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ option_name: optionName, _wpnonce: aceAjax.nonce })
        })
        .then((response) => {
            if (!response.success) {
                console.error(`Failed to delete choice ${choice.name}:`, response.error);
            }
        })
        .catch((error) => console.error(`Error deleting choice ${choice.name}:`, error));
    });

    // Delete selected tags
    const deleteTagPromises = itemsToDelete.tags.map(tag => {
        return apiFetch({
            path: `/wp/v2/field_tags/${tag}?force=true`, // Add force=true to the URL
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _wpnonce: aceAjax.nonce })
        })
        .then((response) => {
            if (!response.deleted) {
                console.error(`Failed to delete tag ${tag.name}:`, response.error);
            }
        })
        .catch((error) => console.error(`Error deleting tag ${tag.name}:`, error));
    });

    // Wait for all deletions to complete
    Promise.all([...deleteChoicePromises, ...deleteTagPromises])
        .then(() => {
            // Clear selections after deletion
            setSelectedChoices([]);
            setSelectedTags([]);
            // Refresh choices and tags to reflect changes
            fetchChoices();
        })
        .catch(error => console.error('Error during bulk deletion:', error))
        .finally(() => setIsBulkDeleteConfirmDialogOpen(false));
};


const handleTagSelect = (tagId) => {
    setSelectedTags(prevSelectedTags =>
        prevSelectedTags.includes(tagId)
            ? prevSelectedTags.filter(id => id !== tagId)
            : [...prevSelectedTags, tagId]
    );
};

const handleChoiceSelect = (choiceName) => {
    setSelectedChoices(prevSelectedChoices =>
        prevSelectedChoices.includes(choiceName)
            ? prevSelectedChoices.filter(name => name !== choiceName)
            : [...prevSelectedChoices, choiceName]
    );
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
        if (isAutoSaveEnabled) {
            throttle(handleSave, 2000)(); // Throttle the save function for text inputs
        }
    }
};



const handleSave = (isAutoSave = false) => {
    setButtonText('Saving...'); // Change button text
    setMessage(''); // Reset message visibility for the saving process

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
                    saved: true // Mark the option as saved
                })) : [],
                show_in_rest: choice.show_in_rest ? 1 : 0,
                tags: choice.tags, // Ensure tags are saved as IDs
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
            // Handle successful save
            setChangedChoices([]); // Reset changed choices
            fetchChoices(); // Refresh choices
            setUnsavedChanges(false); // Reset unsaved changes
            setMessage('Time since last saved'); // Always show time since last saved

            // Start the timer for elapsed time
            const id = setInterval(() => {
                setElapsedTime((prev) => prev + 1);
            }, 1000);
            setIntervalId(id);

            // Change button text to "Fields Saved" for 3 seconds
            setButtonText('Fields Saved');
            setTimeout(() => {
                setButtonText(saveText); // Reset to original text
            }, 3000);

            return { success: true }; // Return success response
        } else {
            console.error('Error saving choices:', response.error);
            setButtonText('Failed!'); // Change button text on failure
            return { success: false }; // Return failure response
        }
    }).catch((error) => {
        console.error('Error saving choices:', error);
        setButtonText('Failed!'); // Handle error
        return { success: false }; // Return failure response
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
            const { choices: fetchedChoices, totalPages: updatedTotalPages, tags: fetchedTags, totalChoices } = response;

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
            setTotalFields(totalChoices);
            console.log(totalChoices); // Update total fields count from API
            if (navigateToLastPage) {
                setCurrentPage(updatedTotalPages); // Navigate to the last page
            }
        })
        .catch((error) => console.error('Error fetching choices:', error));
};

useEffect(() => {
    fetchChoices();
}, [fieldTypeFilter, tagFilter, searchQuery, currentPage, sortBy]);

    

const handleAddChoiceGroup = () => {
    setIsEditingTag(false);
    setIsModalOpen(true);
};

const handleEditTag = (tag) => {
    const updatedTag = {
        id: tag.id || 'default-id',
        label: tag.label || 'Default Label',  // Ensure to use tag.name if available
        description: tag.description || 'Default Description',
        meta: {
            color: tag.meta?.color || '#0073aa',
            associated_roles: Array.isArray(tag.meta?.associated_roles) 
                ? tag.meta.associated_roles 
                : [], // Ensure it's an array
            post_types: Array.isArray(tag.meta?.post_types) 
                ? tag.meta.post_types 
                : [], // Ensure it's an array
        }
    };
    console.log("Updated tag in handleEditTag:", updatedTag); // Debugging log
    setCurrentTag(updatedTag);
    setIsEditingTag(true);
    setIsModalOpen(true);
};


const fetchTagDataAndEdit = async (tagId) => {
    try {
        const tag = await apiFetch({ path: `/wp/v2/field_tags/${tagId}` });
        handleEditTag(tag); // Only call after data is fully fetched
    } catch (error) {
        console.error("Error fetching tag data:", error);
    }
};




    const handleModalSubmit = () => {
        if (isEditingTag) {
            const updatedTag = {
                id: currentTag.id,
                name: currentTag.label,
                description: currentTag.description,
                meta: {
                    color: Array.isArray(currentTag.meta.color) ? currentTag.meta.color[0] : currentTag.meta.color,
                    associated_roles: currentTag.meta.associated_roles || [], // Ensure this is always an array
                    post_types: currentTag.meta.post_types || [], // Ensure this is always an array
                }
            };
    
            apiFetch({
                path: `/wp/v2/field_tags/${updatedTag.id}`,
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(updatedTag),
            })
            .then(response => {
                if (response.id) {
                    setIsModalOpen(false);
                    setCurrentTag(null);
                    fetchChoices({ navigateToLastPage: true });
                } else {
                    console.error('Error updating tag:', response);
                }
            })
            .catch(error => {
                console.error('Error updating tag:', error);
            });
        } else {
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
    }
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
    

    const openConfirmDialog = (choiceName) => {
        const confirmDelete = window.confirm(`Are you sure you want to delete ${choiceName}? This action cannot be undone!`);
        if (confirmDelete) {
            handleConfirmDelete(choiceName);
        }
    };
    
    const handleConfirmDelete = (choiceName) => {
        if (!choiceName) {
            console.error("Choice name is undefined or null.");
            return;
        }
    
        const processedGroupName = createLowercaseUnderscored(choiceName);
        const optionName = 'choices_' + processedGroupName;
    
        apiFetch({
            path: '/ace/v1/delete-option',
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ option_name: optionName, _wpnonce: aceAjax.nonce })
        })
        .then((response) => {
            if (response.success) {
                setChoices((prevChoices) => 
                    prevChoices.filter((choice) => choice.name !== choiceName)
                );
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
    
            if (isAutoSaveEnabled) {
                handleSave(); // Trigger the save function immediately for dropdowns and other inputs
            }
        }
    };
    

    const addOption = (choice) => {
        const newOption = { label: '', value: '', isNew: true, disabled: false }; // Default to not disabled
    
        if (isModalOpen) {
            setNewChoice((prevNewChoice) => ({
                ...prevNewChoice,
                options: [...prevNewChoice.options, newOption]
            }));
        } else {
            setChoices((prevChoices) => {
                const updatedChoices = [...prevChoices];
                const choiceIndex = updatedChoices.findIndex((c) => c.name === choice.name);
    
                if (choiceIndex !== -1) {
                    updatedChoices[choiceIndex].options.push(newOption);
                    markAsChanged(choice.name); // Mark as changed
                }
    
                return updatedChoices;
            });
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

    const toggleOpen = (cardId, groupId) => {
        setOpenCardIds((prev) => {
                // When grouped by tag, only open the clicked card within its specific tag group
                const uniqueId = `${groupBy}-${cardId}-${groupId || 'default'}`;
                const isOpen = prev.includes(uniqueId);
                return isOpen 
                    ? prev.filter(id => id !== uniqueId) // Close if already open
                    : [uniqueId]; // Only open the clicked instance within its tag group

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
                totalFields={totalFields} 
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
                isEditingTag={isEditingTag}
                handleEditTag={handleEditTag}
                fetchTagDataAndEdit={fetchTagDataAndEdit}
                setCurrentTag={setCurrentTag}
                currentTag={currentTag}
                setGroupBy={setGroupBy}
                setNewChoice={setNewChoice}
                setIsAutoSaveEnabled={setIsAutoSaveEnabled}
                isAutoSaveEnabled={isAutoSaveEnabled}
                buttonText={buttonText}
                setButtonText={setButtonText}
                setMessage={setMessage}
                handleBulkDelete={handleBulkDelete}
                selectedChoices={selectedChoices} // Pass selectedChoices
                handleChoiceSelect={handleChoiceSelect} 
                selectedTags={selectedTags} // Pass selectedTags
                handleTagSelect={handleTagSelect}
                quickEdit={quickEdit}
                setQuickEdit={setQuickEdit}
                setTags={setTags}
                getRandomColor={getRandomColor}
                customStyles={customStyles}
                setSelectedTags={setSelectedTags}
                aceAjax={aceAjax}
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

{isBulkDeleteConfirmDialogOpen && (
                <ConfirmDialog
                    isOpen={isBulkDeleteConfirmDialogOpen}
                    onConfirm={confirmBulkDelete}
                    onCancel={() => setIsBulkDeleteConfirmDialogOpen(false)}
                    confirmButtonText="Yes, Delete"
                    cancelButtonText="Cancel"
                >
                    <p>{bulkDeleteConfirmMessage}</p>
                    <ul>
                        {itemsToDelete.choices.map(choice => (
                            <li key={choice.name}>{choice.name}</li>
                        ))}
                        {itemsToDelete.tags.map(tag => (
                            <li key={tag.id}>{tag.name}</li>
                        ))}
                    </ul>
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
