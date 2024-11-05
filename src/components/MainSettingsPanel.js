import { useState, useEffect } from '@wordpress/element';
import { Button, Modal, Flex, FlexBlock, FlexItem, TextControl, ColorPicker, CheckboxControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import apiFetch from '@wordpress/api-fetch';

import SideBar from './SideBar';
import ChoiceCards from './ChoiceCards'; // Adjust path as necessary
import ChoiceFields from './ChoiceFields'; // Adjust the path as needed
import SaveBar from './SaveBar/SaveBar'; // Adjust the path as needed
import SettingsHeader from './SettingsHeader';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEye, faCheckSquare, faToggleOn, faCircleDot, faFont, faSquareCaretDown } from '@fortawesome/free-solid-svg-icons';

import Select from 'react-select';

import Creatable from 'react-select/creatable';


const MainSettingsPanel = ({
    choices,
    newlyAddedChoice,
    openIndexes,
    toggleOpen,
    handleTextChange,
    handleToggleChange,
    handleTagChange,
    updateOption,
    removeOption,
    addOption,
    handleFieldTypeChange,
    fieldTypeFilter,
    currentPage,
    totalPages,
    handlePreviousPage,
    handleNextPage,
    handleSave,
    setUnsavedChanges,
    unsavedChanges,
    handleAddChoiceGroup,
    isModalOpen,
    setIsModalOpen,
    newChoice,
    handleModalSubmit,
    openConfirmDialog,
    formatRelativeTime,
    setFieldTypeFilter,
    tagFilter,
    setTagFilter, // Accept the setter function
    tags, 
    searchQuery,
    handleSearchChange,
    isEditingTag,
    currentTag,
    handleEditTag,
    setCurrentTag,
    groupBy,
    setGroupBy,
    setIsAutoSaveEnabled,
    isAutoSaveEnabled,
    setNewChoice,
    setButtonText,
    buttonText,
    setMessage,
    totalFields,
    handleBulkDelete,
    selectedChoices,
    handleChoiceSelect,
    selectedTags,
    handleTagSelect,
    quickEdit,
    setQuickEdit,
    setTags,
    customStyles,
    getRandomColor,
    setSelectedTags
}) => {
    // Define the groupBy state and its handler
    const [sortBy, setSortBy] = useState('edited'); // or whatever default sort



    const tagOptions = Object.keys(tags).map(tagId => ({
        value: tagId,
        label: tags[tagId].name
    }));
    


    const fieldTypeIcons = {
        text: faFont,
        checkbox: faCheckSquare,
        select: faSquareCaretDown,
        radio: faCircleDot,
        truefalse: faToggleOn,
    };


    const handleGroupByChange = (newGroupBy) => {
        setGroupBy(newGroupBy);
    };

    const handleSortChange = (value) => {
        setSortBy(value);
    };

    const sortChoices = (choices, sortBy) => {
        return [...choices].sort((a, b) => {
            switch(sortBy) {
                case 'edited':
                    return new Date(b.modified_time) - new Date(a.modified_time);
                case 'title':
                    return a.name.localeCompare(b.name);
                default:
                    return a.name.localeCompare(b.name);
            }
        });
    };


    const [color, setColor] = useState(currentTag?.meta?.color || '');

    useEffect(() => {
        if (currentTag?.meta?.color) {
            // Check if color is an array
            if (Array.isArray(currentTag.meta.color)) {
                setColor(currentTag.meta.color[0] || '#000'); // Set to first element or fallback
            } else {
                setColor(currentTag.meta.color || '#000'); // Set directly or fallback
            }
        } else {
            setColor('#000'); // Fallback to default if no color is set
        }
    }, [currentTag]);

    
    const handleColorChangeComplete = (value) => {
        setColor(value.hex); // Update local color state
        setCurrentTag((prevTag) => ({
            ...prevTag,
            color: value.hex,
            meta: {
                ...prevTag.meta,
                color: value.hex, // Update meta.color only
            },
        }));
    };


    const visibleFields = choices.filter(field => field); // Adjust this filter as needed


       
      
      
      
    const [availableRoles, setAvailableRoles] = useState([]);
    const [availablePostTypes, setAvailablePostTypes] = useState([]);

    // Fetch post types using @wordpress/core-data
    const postTypes = useSelect((select) =>
        select(coreDataStore)?.getPostTypes({ per_page: -1 }) || [],
    []);

    useEffect(() => {
        // Set available post types once they're fetched
        if (postTypes.length) {
            setAvailablePostTypes(
                postTypes.map((postType) => ({
                    label: postType.name,
                    value: postType.slug,
                }))
            );
        }
    }, [postTypes]);


useEffect(() => {
    const fetchRoles = async () => {
        try {
            const rolesData = await apiFetch({
                path: '/ace/v1/roles',
                method: 'GET',
                headers: { 'X-WP-Nonce': aceAjax.nonce }
            });

            setAvailableRoles(
                rolesData.map((role) => ({
                    label: role.name,
                    value: role.slug,
                }))
            );
        } catch (error) {
            console.error('Error fetching roles:', error);
        }
    };

    fetchRoles();
}, []);


const handleMetaChange = (key, value) => {
    setCurrentTag((prevTag) => {
        const { associated_roles = [], post_types = [], color } = prevTag.meta;

        let updatedMeta = { ...prevTag.meta }; // Start with the current meta

        if (key === 'associated_roles') {
            // Toggle role
            updatedMeta.associated_roles = associated_roles.includes(value)
                ? associated_roles.filter((r) => r !== value) // Remove role
                : [...associated_roles, value]; // Add role
        } else if (key === 'post_types') {
            // Toggle post type
            updatedMeta.post_types = post_types.includes(value)
                ? post_types.filter((p) => p !== value) // Remove post type
                : [...post_types, value]; // Add post type
        } else if (key === 'color') {
            // Update color
            updatedMeta.color = value;
        }

        console.log(updatedMeta); // Debugging log to check the updated meta

        return {
            ...prevTag,
            meta: updatedMeta, // Update the meta with the changes
        };
    });
};

      

    return (
        <>
            <Flex gap={6} className="main-panel">
                <FlexItem style={{ alignSelf: 'flex-start', maxWidth: '25%' }} className="side-panel">
                    <SettingsHeader handleAddChoiceGroup={handleAddChoiceGroup} />
                    <SideBar 
                        handleSortChange={handleSortChange}
                        sortBy={sortBy}
                        fieldTypeFilter={fieldTypeFilter}
                        setFieldTypeFilter={setFieldTypeFilter}
                        tagFilter={tagFilter}
                        setTagFilter={setTagFilter} // Pass the setter function
                        tags={tags} // Pass the tags state
                        groupBy={groupBy}
                        handleGroupByChange={handleGroupByChange}
                        fields={choices}
                        formatRelativeTime={formatRelativeTime}
                        searchQuery={searchQuery}
                        handleSearchChange={handleSearchChange} 
                        isEditingTag={isEditingTag}
                        handleEditTag={handleEditTag}
                        setCurrentTag={setCurrentTag}
                        currentTag={currentTag}
                        totalFields={totalFields} 
                        setSelectedTags={setSelectedTags}
                quickEdit={quickEdit}
                setQuickEdit={setQuickEdit}
                handleBulkDelete={handleBulkDelete}
                selectedTags={selectedTags} // Pass the selectedTags
                handleTagSelect={handleTagSelect}
                getRandomColor={getRandomColor}
                customStyles={customStyles}
                    />
                </FlexItem>

                <FlexBlock>
                    {/* Choice Cards */}

                    {choices.length !== 0 && (
                    <div style={{ marginBottom: '15px' }} className='currently-viewing'>
                <strong>                            <FontAwesomeIcon icon={faEye} style={{ marginRight: '5px' }} />
                Viewing:</strong> {visibleFields.length} of {totalFields}
                <ul className='visible-types'>
    {Object.entries(visibleFields.reduce((acc, field) => {
        acc[field.field_type] = (acc[field.field_type] || 0) + 1;
        return acc;
    }, {})).map(([type, count]) => (
        <li key={type}>
            <FontAwesomeIcon icon={fieldTypeIcons[type] || faGlobe} style={{ marginRight: '5px' }} />
            {type.charAt(0).toUpperCase() + type.slice(1)}: <strong>{count}</strong>
        </li>
    ))}
</ul>
            </div>
        )}

                    <ChoiceCards
                        choices={choices}
                        sortBy={sortBy}
                        groupBy={groupBy}  // Add this line
                        newlyAddedChoice={newlyAddedChoice}
                        openIndexes={openIndexes}
                        toggleOpen={toggleOpen}
                        handleTextChange={handleTextChange}
                        handleToggleChange={handleToggleChange}
                        handleTagChange={handleTagChange}
                        updateOption={updateOption}
                        removeOption={removeOption}
                        addOption={addOption}
                        isModalOpen={isModalOpen}
                        openConfirmDialog={openConfirmDialog}
                        formatRelativeTime={formatRelativeTime}
                        tags={tags}
                        currentTag={currentTag}
                        quickEdit={quickEdit}
                        setQuickEdit={setQuickEdit}
                        handleBulkDelete={handleBulkDelete}
                        selectedChoices={selectedChoices} // Pass selectedChoices
                        handleChoiceSelect={handleChoiceSelect} 
                        handleAddChoiceGroup={handleAddChoiceGroup}
                        setNewChoice={setNewChoice}
                        setTags={setTags}
                        getRandomColor={getRandomColor}
                        customStyles={customStyles}
                    />

                    {totalPages > 1 && (
                        <Flex justify="flex-start" gap={2}>
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
                        </Flex>
                    )}
                </FlexBlock>
            </Flex>

            <SaveBar
    onSave={handleSave}
    setUnsavedChanges={setUnsavedChanges}
    unsavedChanges={unsavedChanges}
    isAutoSaveEnabled={isAutoSaveEnabled} // Pass the state to SaveBar
    setIsAutoSaveEnabled={setIsAutoSaveEnabled} // Pass the function to SaveBar
    buttonText={unsavedChanges ? 'Save Changes' : 'Saved'}
    setButtonText={setButtonText}
    setMessage={setMessage}
>

{quickEdit != 'true' && (
    <Button isPrimary variant="secondary" onClick={handleAddChoiceGroup} icon="plus">
        Add New Field
    </Button>
)}

{quickEdit === 'true' && (
    <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
        <Button isDestructive isPrimary onClick={handleBulkDelete} icon="trash">
            Delete Selected
        </Button>
        


    <label htmlFor="bulk-tag-set">Select or Create Tags</label>
        <Creatable
    isMulti
     menuPlacement="top"
     id="bulk-tag-set"
    options={tagOptions}
    value={selectedTags}
    className="ace-tag-select"
    styles={customStyles} // Apply custom styles to display tag color
    onChange={(selectedOptions, actionMeta) => {
        if (actionMeta.action === 'create-option') {
            const temporaryTagId = `temp-${Date.now()}`;
            const randomColor = getRandomColor();
            const newTag = { name: actionMeta.option.label, description: '', meta: { color: randomColor } };
            const temporaryTag = { value: temporaryTagId, label: newTag.name, meta: { color: randomColor } };

            // Optimistically update tag options and selected tags
            const updatedTagOptions = [...tagOptions, temporaryTag];
            const updatedSelectedTags = [...selectedTags, temporaryTag];
            setTags((prevTags) => ({
                ...prevTags,
                [temporaryTagId]: { name: newTag.name, description: '', meta: { color: randomColor } },
            }));
            handleTagChange(choice, updatedSelectedTags.map(tag => tag.value));
            setNewChoice((prevNewChoice) => ({
                ...prevNewChoice,
                tags: updatedSelectedTags.map(tag => tag.value),
            }));

            // Proceed with the API call to create a new tag
            apiFetch({
                path: '/wp/v2/field_tags',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(newTag),
            })
                .then((response) => {
                    if (response.id) {
                        const createdTag = { value: response.id, label: response.name, meta: { color: randomColor } };

                        // Update tags to replace temporary tag with the saved tag
                        setTags((prevTags) => {
                            const { [temporaryTagId]: removed, ...restTags } = prevTags;
                            return {
                                ...restTags,
                                [response.id]: { name: response.name, description: '', meta: { color: randomColor } },
                            };
                        });

                        handleTagChange(
                            choice,
                            [
                                ...updatedSelectedTags.filter((tag) => tag.value !== temporaryTagId),
                                createdTag,
                            ].map((tag) => tag.value)
                        );
                    } else {
                        console.error('Error creating tag:', response);
                    }
                })
                .catch((error) => {
                    console.error('Error creating tag:', error);
                    // Optionally handle error by removing the temporary tag
                    setTags((prevTags) => {
                        const { [temporaryTagId]: removed, ...restTags } = prevTags;
                        return restTags;
                    });
                });
        } else {
            // Handle selection of existing tags
            const tagIds = selectedOptions.map((option) => option.value);
            handleTagChange(choice, tagIds);
        }
    }}
/>

    </div>
)}

            </SaveBar>

            {/* Modal for adding a new choice group */}
            {isModalOpen && (
    <Modal
        title={isEditingTag ? "Edit Tag" : "Add New Choice Group"}
        onRequestClose={() => setIsModalOpen(false)}
        size="large"
        focusOnMount="firstContentElement"
    >
        {isEditingTag ? (
             <>
             {console.log(currentTag)}
             <TextControl
                 label="Tag Name"
                 value={currentTag ? currentTag.label : ''}
                 onChange={(value) => setCurrentTag({ ...currentTag, label: value })}
             />
             <TextControl
                 label="Tag Description"
                 value={currentTag ? currentTag.description : ''}
                 onChange={(value) => setCurrentTag({ ...currentTag, description: value })}
             />


<h4>Associated Roles</h4>
<Flex wrap="wrap" gap={4}>
    {availableRoles.map((role) => (
        <FlexItem key={role.value} style={{ width: '30%', minWidth: '150px' }}>
            <ToggleControl
                label={role.label}
                checked={Array.isArray(currentTag?.meta?.associated_roles) && currentTag.meta.associated_roles.includes(role.value)}
                onChange={() => handleMetaChange('associated_roles', role.value)} // Use the unified function
            />
        </FlexItem>
    ))}
</Flex>

<h4>Associated Post Types</h4>
<Flex wrap="wrap" gap={4}>
    {availablePostTypes.map((postType) => (
        <FlexItem key={postType.value} style={{ width: '30%', minWidth: '150px' }}>
            <ToggleControl
                label={postType.label}
                checked={Array.isArray(currentTag?.meta?.post_types) && currentTag.meta.post_types.includes(postType.value)}
                onChange={() => handleMetaChange('post_types', postType.value)} // Use the unified function
            />
        </FlexItem>
    ))}
</Flex>

{currentTag?.meta.color || '#000'} 

<ColorPicker
    color={color} // Fallback to '#000' if currentTag.color is not set
    disableAlpha={true}
    onChangeComplete={(colorValue) => handleMetaChange('color', colorValue.hex)} // Use the unified function for color change
/>
         </>
        ) : (
            <ChoiceFields 
                choice={newChoice}
                handleTextChange={handleTextChange}
                handleToggleChange={handleToggleChange}
                handleTagChange={handleTagChange}
                updateOption={updateOption}
                removeOption={removeOption}
                addOption={addOption}
                isModalOpen={isModalOpen}
                openConfirmDialog={openConfirmDialog}
                tags={tags}
                setNewChoice={setNewChoice}
                getRandomColor={getRandomColor}
                customStyles={customStyles}
            />
        )}
        <Button isPrimary variant="primary" onClick={handleModalSubmit}>
            {isEditingTag ? "Save Tag" : "Add Group"}
        </Button>
    </Modal>
)}
        </>
    );
};

export default MainSettingsPanel;


