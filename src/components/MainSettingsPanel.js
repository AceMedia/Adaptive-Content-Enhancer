import { useState, useEffect } from '@wordpress/element';
import { Button, Modal, Flex, FlexBlock, FlexItem, TextControl, ColorPicker  } from '@wordpress/components';
import SideBar from './SideBar';
import ChoiceCards from './ChoiceCards'; // Adjust path as necessary
import ChoiceFields from './ChoiceFields'; // Adjust the path as needed
import SaveBar from './SaveBar/SaveBar'; // Adjust the path as needed
import SettingsHeader from './SettingsHeader';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEye, faCheckSquare, faToggleOn, faCircleDot, faFont, faSquareCaretDown } from '@fortawesome/free-solid-svg-icons';


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
    setQuickEdit
}) => {
    // Define the groupBy state and its handler
    const [sortBy, setSortBy] = useState('edited'); // or whatever default sort


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
            setColor(currentTag.meta.color);
        }
        console.log('currentTag', currentTag);
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

                quickEdit={quickEdit}
                setQuickEdit={setQuickEdit}
                handleBulkDelete={handleBulkDelete}
                selectedTags={selectedTags} // Pass the selectedTags
                handleTagSelect={handleTagSelect}
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
                        quickEdit={quickEdit}
                        setQuickEdit={setQuickEdit}
                        handleBulkDelete={handleBulkDelete}
                        selectedChoices={selectedChoices} // Pass selectedChoices
                        handleChoiceSelect={handleChoiceSelect} 
                        handleAddChoiceGroup={handleAddChoiceGroup}
                    />

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
                <Button isPrimary variant="secondary" onClick={handleAddChoiceGroup} icon="plus">
                    Add New Field
                </Button>
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
             <TextControl
                 label="Tag Name"
                 value={currentTag ? currentTag.label : ''}
                 onChange={(value) => setCurrentTag({ ...currentTag, label: value })}
             />
             <TextControl
                 label="Tag Description"
                 value={currentTag ? currentTag.description : ''}
                 onChange={(value) => setCurrentTag({ ...currentTag, description: value })}
             />{currentTag?.meta.color || '#000'}
           
           <ColorPicker
    color={color} // Fallback to '#000' if currentTag.color is not set
    disableAlpha={true} // Set disableAlpha to true to disable the alpha slider
    onChangeComplete={handleColorChangeComplete}
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


