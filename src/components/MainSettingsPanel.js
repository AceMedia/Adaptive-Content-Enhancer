import { useState } from '@wordpress/element';
import { Button, Modal, Flex, FlexBlock, FlexItem, TextControl } from '@wordpress/components';
import SideBar from './SideBar';
import ChoiceCards from './ChoiceCards'; // Adjust path as necessary
import ChoiceFields from './ChoiceFields'; // Adjust the path as needed
import SaveBar from './SaveBar/SaveBar'; // Adjust the path as needed
import SettingsHeader from './SettingsHeader';


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
}) => {
    // Define the groupBy state and its handler
    const [sortBy, setSortBy] = useState('edited'); // or whatever default sort

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
                    />
                </FlexItem>

                <FlexBlock style={{ alignSelf: 'flex-start' }} >
                    {/* Choice Cards */}
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


