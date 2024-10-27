import { Button, Modal } from '@wordpress/components';
import SideBar from './SideBar';
import ChoiceCards from './ChoiceCards'; // Adjust path as necessary
import ChoiceFields from './ChoiceFields'; // Adjust the path as needed
import SaveBar from './SaveBar/SaveBar'; // Adjust the path as needed

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
}) => (
    <div className="wrap">
        <h1>Adaptive Content Enhancer Settings</h1>

        {/* Sidebar */}
        <SideBar 
            handleSortChange={handleSortChange} 
            fieldTypeFilter={fieldTypeFilter} 
            handleFieldTypeChange={handleFieldTypeChange} 
        />

        {/* Choice Cards */}
        <ChoiceCards
            choices={choices}
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

        {/* Save Bar */}
        <SaveBar 
            onSave={handleSave} 
            setUnsavedChanges={setUnsavedChanges} 
            unsavedChanges={unsavedChanges}
        >
            <Button isPrimary variant="secondary" onClick={handleAddChoiceGroup}>
                Add Options
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


    </div>
);

export default MainSettingsPanel;
