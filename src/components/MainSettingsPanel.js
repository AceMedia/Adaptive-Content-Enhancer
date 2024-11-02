import { useState } from '@wordpress/element';
import { Button, Modal, Flex, FlexBlock, FlexItem } from '@wordpress/components';
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
    handleSortChange,
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
}) => {
    // Define the groupBy state and its handler
    const [groupBy, setGroupBy] = useState('nothing'); // Default grouping option

    const handleGroupByChange = (newGroupBy) => {
        setGroupBy(newGroupBy);
    };

    return (
        <>
            <Flex gap={6} className="main-panel">
                <FlexItem style={{ alignSelf: 'flex-start', maxWidth: '25%' }} className="side-panel">
                    <SettingsHeader handleAddChoiceGroup={handleAddChoiceGroup} />
                    <SideBar 
                        fields={choices} 
                        handleSortChange={handleSortChange} 
                        fieldTypeFilter={fieldTypeFilter} 
                        handleFieldTypeChange={handleFieldTypeChange} 
                        formatRelativeTime={formatRelativeTime} 
                        groupBy={groupBy}
                        handleGroupByChange={handleGroupByChange} // Pass the handler
                    />
                </FlexItem>

                <FlexBlock>
                    {/* Choice Cards */}
                    <ChoiceCards
                        choices={choices}
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
                    />

                    {/* Pagination Buttons */}
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
            >
                <Button isPrimary variant="secondary" onClick={handleAddChoiceGroup} icon="plus">
                    Add New Field
                </Button>
            </SaveBar>

            {/* Modal for adding a new choice group */}
            {isModalOpen && (
                <Modal
                    title="Add New Choice Group"
                    onRequestClose={() => setIsModalOpen(false)}
                    size="large"
                    focusOnMount="firstContentElement"
                >
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
                    />
                    <Button isPrimary variant="primary" onClick={handleModalSubmit}>
                        Add Group
                    </Button>
                </Modal>
            )}
        </>
    );
};

export default MainSettingsPanel;
