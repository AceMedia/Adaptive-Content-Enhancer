import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { Button, Modal, Flex, FlexBlock, FlexItem, TextControl, ColorPicker, Tooltip, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import apiFetch from '@wordpress/api-fetch';

import SideBar from './SideBar';
import ChoiceCards from './ChoiceCards';
import ChoiceFields from './ChoiceFields';
import SaveBar from './SaveBar/SaveBar';
import SettingsHeader from './SettingsHeader';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEye, faCheckSquare, faToggleOn, faCircleDot, faFont, faSquareCaretDown, faClock } from '@fortawesome/free-solid-svg-icons';

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
    setTagFilter,
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
    message,
    totalFields,
    handleBulkDelete,
    selectedFields,
    handleChoiceSelect,
    selectedTags,
    handleTagSelect,
    quickEdit,
    setQuickEdit,
    setTags,
    customStyles,
    getRandomColor,
    setSelectedTags,
    responseTime
}) => {
    const [sortBy, setSortBy] = useState('edited');
    const [availableRoles, setAvailableRoles] = useState([]);
    const [availablePostTypes, setAvailablePostTypes] = useState([]);
    const [color, setColor] = useState(currentTag?.meta?.color || '#000');



    const visibleFields = choices.filter(field => field); // Adjust this filter as needed


    const fieldTypeIcons = useMemo(() => ({
        text: faFont,
        checkbox: faCheckSquare,
        select: faSquareCaretDown,
        radio: faCircleDot,
        truefalse: faToggleOn,
    }), []);

    const tagOptions = useMemo(() => Object.keys(tags).map(tagId => ({
        value: tagId,
        label: tags[tagId].name
    })), [tags]);

    const handleGroupByChange = useCallback((newGroupBy) => {
        setGroupBy(newGroupBy);
    }, [setGroupBy]);

    const handleSortChange = useCallback((value) => {
        setSortBy(value);
    }, []);

    const sortedFields = useMemo(() => {
        return [...choices].sort((a, b) => {
            switch (sortBy) {
                case 'edited':
                    return new Date(b.modified_time) - new Date(a.modified_time);
                case 'title':
                    return a.name.localeCompare(b.name);
                default:
                    return a.name.localeCompare(b.name);
            }
        });
    }, [choices, sortBy]);

    const postTypes = useSelect((select) => 
        select(coreDataStore)?.getPostTypes({ per_page: -1 }) || [],
    );

    useEffect(() => {
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
                const rolesData = await apiFetch({ path: '/ace/v1/roles', method: 'GET', headers: { 'X-WP-Nonce': aceAjax.nonce } });
                setAvailableRoles(rolesData.map((role) => ({ label: role.name, value: role.slug })));
            } catch (error) {
                console.error('Error fetching roles:', error);
            }
        };
        fetchRoles();
    }, []);

    useEffect(() => {
        if (currentTag?.meta?.color) {
            setColor(Array.isArray(currentTag.meta.color) ? currentTag.meta.color[0] || '#000' : currentTag.meta.color || '#000');
        } else {
            setColor('#000');
        }
    }, [currentTag]);

    const handleMetaChange = useCallback((key, value) => {
        setCurrentTag((prevTag) => {
            const { associated_roles = [], post_types = [] } = prevTag.meta;
            let updatedMeta = { ...prevTag.meta };

            if (key === 'associated_roles') {
                updatedMeta.associated_roles = associated_roles.includes(value)
                    ? associated_roles.filter((r) => r !== value)
                    : [...associated_roles, value];
            } else if (key === 'post_types') {
                updatedMeta.post_types = post_types.includes(value)
                    ? post_types.filter((p) => p !== value)
                    : [...post_types, value];
            } else if (key === 'color') {
                updatedMeta.color = value;
            }

            updatedMeta.associated_roles = updatedMeta.associated_roles.map(String);
            updatedMeta.post_types = updatedMeta.post_types.map(String);
            return { ...prevTag, meta: updatedMeta };
        });
    }, [setCurrentTag, availableRoles, availablePostTypes]);

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
                        setTagFilter={setTagFilter}
                        tags={tags}
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
                        selectedTags={selectedTags}
                        handleTagSelect={handleTagSelect}
                        getRandomColor={getRandomColor}
                        customStyles={customStyles}
                    />
                </FlexItem>
                
                <FlexBlock>


{quickEdit === 'true' && (
<div style={{ display: 'flex', alignItems: 'center', gap: '1em', marginBottom: '15px' }}>
<ToggleControl
label="Toggle All"
checked={choices.every(choice => choice.isSelected)} // Ensure checked reflects all choices
onChange={(value) => {
handleToggleChange('toggle_all', value); // Use 'toggle_all' as the choice identifier
}}
/>
</div>
)}


    {choices.length !== 0 && (
        <div style={{ marginBottom: '15px' }} className='currently-viewing'>
            <Tooltip text={`Viewing ${visibleFields.length} of ${totalFields}`}></Tooltip>



<Tooltip text={`Viewing ${visibleFields.length} of ${totalFields}`}>
    <span className='pill' style={{ marginRight: '1em' }} >
        <FontAwesomeIcon icon={faEye} style={{ marginRight: '5px' }} />{visibleFields.length} of {totalFields}
    </span>
</Tooltip>


<Tooltip text={`${responseTime}ms response time for the current view`}>
                <span className='pill'>
                    <FontAwesomeIcon icon={faClock} style={{ marginRight: '5px' }} />
                    {responseTime}ms
                </span>
            </Tooltip>


<ul className='visible-types'>
{Object.entries(visibleFields.reduce((acc, field) => {
acc[field.field_type] = (acc[field.field_type] || 0) + 1;
return acc;
}, {})).map(([type, count]) => (
<li key={type}>
<Tooltip text={`Showing ${count} ${type.charAt(0).toUpperCase() + type.slice(1)} fields`}>
<span className='pill'>
<FontAwesomeIcon icon={fieldTypeIcons[type] || faGlobe} style={{ marginRight: '5px' }} />
<strong>{count}</strong>
    </span>
</Tooltip>
</li>
))}
</ul>
</div>
)}


                    <ChoiceCards
                        choices={sortedFields}
                        sortBy={sortBy}
                        groupBy={groupBy}
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
                        selectedFields={selectedFields}
                        handleChoiceSelect={handleChoiceSelect} 
                        handleAddChoiceGroup={handleAddChoiceGroup}
                        setNewChoice={setNewChoice}
                        setTags={setTags}
                        getRandomColor={getRandomColor}
                        customStyles={customStyles}
                    />
                    {totalPages > 1 && (
                        <Flex justify="flex-start" gap={2}>
                            {currentPage > 1 && <Button isSecondary onClick={handlePreviousPage}>Previous</Button>}
                            {currentPage < totalPages && <Button isSecondary onClick={handleNextPage}>Next</Button>}
                        </Flex>
                    )}
                </FlexBlock>
            </Flex>

            <SaveBar
                onSave={handleSave}
                setUnsavedChanges={setUnsavedChanges}
                unsavedChanges={unsavedChanges}
                isAutoSaveEnabled={isAutoSaveEnabled}
                setIsAutoSaveEnabled={setIsAutoSaveEnabled}
                buttonText={unsavedChanges ? 'Save Changes' : 'Saved'}
                message={message}
            >
                {quickEdit !== 'true' && (
                    <Button isPrimary variant="secondary" onClick={handleAddChoiceGroup} icon="plus">
                        Add New Field
                    </Button>
                )}
                {quickEdit === 'true' && (
                    <div style={{ display: 'flex', alignItems: 'center', gap: '1em' }}>
                        <Button isDestructive isPrimary onClick={handleBulkDelete} icon="trash">
                            Delete Selected
                        </Button>
                    </div>
                )}
            </SaveBar>

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
                            <ColorPicker
                                color={color}
                                disableAlpha
                                onChangeComplete={(colorValue) => handleMetaChange('color', colorValue.hex)}
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
                        {isEditingTag ? "Save Tag" : "Add Field"}
                    </Button>
                </Modal>
            )}
        </>
    );
};

export default MainSettingsPanel;
