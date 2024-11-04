import { 
    __experimentalToggleGroupControl as ToggleGroupControl,
    __experimentalToggleGroupControlOption as ToggleGroupControlOption,
    PanelBody,
    Button,
    TextControl,
    ToggleControl
} from '@wordpress/components';
import PropTypes from 'prop-types';
import { useState, useEffect } from '@wordpress/element';
import { Icon, filter, info, formatCapitalize } from '@wordpress/icons';
import Select from 'react-select';



import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEdit, faCheckSquare, faToggleOn, faCircleDot, faFont, faSquareCaretDown, faTags, faFilter, faLayerGroup, faCircleQuestion, faGlobe, faSort } from '@fortawesome/free-solid-svg-icons';



const fieldTypeIcons = {
    text: faFont,
    checkbox: faCheckSquare,
    select: faSquareCaretDown,
    radio: faCircleDot,
    truefalse: faToggleOn,
    default: faGlobe,
};


const SearchComponent = ({ searchQuery, handleSearchChange }) => {
    return (
        <div className='search-holder'>
        <input
            type="text"
            value={searchQuery}
            onChange={(e) => handleSearchChange(e.target.value)}
            placeholder="Search..."
            style={{ width: '100%', marginBottom: '0px' }}
        />
    <span className="dashicons dashicons-search" style={{ marginLeft: '5px' }}></span>
    </div>
    );
};





const SideBar = ({ 
    handleSortChange, 
    sortBy, 
    fieldTypeFilter, 
    setFieldTypeFilter, 
    handleSearchChange,
    searchQuery,
    tagFilter, // Accept the setter function
    tags,
    setTagFilter, // Accept the setter function
    groupBy,
    handleGroupByChange,
    fields,
    formatRelativeTime,
    handleEditTag,
    currentTag,
    setCurrentTag,
    totalFields,
    quickEdit,
    setQuickEdit,
    handleBulkDelete,
    selectedTags,
    setSelectedTags,
    handleTagSelect,
    getRandomColor,
    customStyles,
}) => {
    const handleFieldTypeChange = (selectedType) => {
        setFieldTypeFilter(prev => {
            if (selectedType === '') {
                return ['']; // Reset filter and set "All Types" as active
            }
            if (prev.includes(selectedType)) {
                const newFilter = prev.filter(type => type !== selectedType);
                return newFilter.length === 0 ? [''] : newFilter; // If no filters left, set "All Types"
            }
            return [...prev.filter(type => type !== ''), selectedType]; // Add type and remove "All Types"
        });
    };



const tagOptions = Object.keys(tags).map(tagId => ({
    value: tagId,
    label: tags[tagId].name
}));



    const handleTagChange = (selectedTag) => {
        setTagFilter(prev => {
            if (selectedTag === '') {
                return ['']; // Reset filter and set "All Tags" as active
            }
            if (prev.includes(selectedTag)) {
                const newFilter = prev.filter(tag => tag !== selectedTag);
                return newFilter.length === 0 ? [''] : newFilter; // If no filters left, set "All Tags"
            }
            return [...prev.filter(tag => tag !== ''), selectedTag]; // Add tag and remove "All Tags"
        });
    };



    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
           <SearchComponent 
                searchQuery={searchQuery} 
                handleSearchChange={handleSearchChange} 
            />



<PanelBody title={
    <span>
        <FontAwesomeIcon icon={faLayerGroup} />Grouping
    </span>
} initialOpen={false}>
    <div style={{ display: 'flex', flexDirection: 'column', gap: '5px' }}>
        <ToggleGroupControl
            label="Group by"
            value={groupBy}
            onChange={handleGroupByChange}
            defaultValue="nothing"
            isBlock
            __nextHasNoMarginBottom
        >
            <ToggleGroupControlOption 
                value="nothing" 
                label="None" 
            />
            <ToggleGroupControlOption 
                value="type" 
                label="Type" 
            />
            <ToggleGroupControlOption 
                value="tag" 
                label="Tag" 
            />
        </ToggleGroupControl>
    </div>
</PanelBody>


<PanelBody title={
                                <span>
                                    <FontAwesomeIcon icon={faSort} />Sorting
                                </span>
                            } initialOpen={false}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '5px' }}>
            <ToggleGroupControl
                label="Sort by"
                value={sortBy}
                onChange={handleSortChange}
                defaultValue="edited"
                isBlock
                __nextHasNoMarginBottom
            >
                <ToggleGroupControlOption 
                    value="edited" 
                    label="Updated" 
                />
                <ToggleGroupControlOption 
                    value="created" 
                    label="Created" 
                />
                <ToggleGroupControlOption 
                    value="title" 
                    label="Name" 
                />
            </ToggleGroupControl>
</div>
</PanelBody>


            
            <PanelBody title={
                                <span>
                                    <FontAwesomeIcon icon={faFilter} />Filter by Field Type
                                </span>
                            } initialOpen={false}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '5px' }}>
                {[
                    { label: 'All Types', value: '' },
                    { label: 'Text', value: 'text' },
                    { label: 'Select', value: 'select' },
                    { label: 'Checkbox', value: 'checkbox' },
                    { label: 'True/False', value: 'truefalse' },
                    { label: 'Radio', value: 'radio' }
                ].map((option) => (
                    <Button
                        key={option.value}
                        isPressed={fieldTypeFilter.includes(option.value)}
                        onClick={() => handleFieldTypeChange(option.value)}
                        className="toggle-button"
                    >
                        
                        <FontAwesomeIcon icon={fieldTypeIcons[option.value] || fieldTypeIcons.default} /> {option.label}
                    </Button>
                ))}
            </div>
        </PanelBody>


        <PanelBody
    title={
        <span>
            <FontAwesomeIcon icon={faTags} /> Filter by Field Tags
        </span>
    }
    initialOpen={false}
>
    <div style={{ display: 'flex', flexDirection: 'column', gap: '5px' }}>
        {[
            { label: 'All Tags', value: '' },
            ...Object.keys(tags).map(tagId => ({
                label: tags[tagId].name,
                value: tagId, // Use the tag ID
                description: tags[tagId].description,
                meta: {
                    color: tags[tagId].meta?.color || '#000' // Use the dynamic color from tags, defaulting to black if missing
                }
            }))
        ].map((option) => (
            <Button
                key={option.value}
                isPressed={tagFilter.includes(option.value)}
                onClick={() => handleTagChange(option.value)}
                className="toggle-button"
                style={{
                    color: option.meta?.color || '#000' // Safely access color with fallback
                }}
            >
                <span
                    className="field-tag-color"
                    style={{
                        background: option.meta?.color || '#000' // Safely use background color with fallback
                    }}
                ></span>
                {option.label}

                {/* Only show the Edit button if it's not the "All Tags" option */}
                {option.value && (
                    <span
                        className="field-tag-count"
                        onClick={(e) => {
                            e.stopPropagation(); // Prevent triggering the parent button
                            handleEditTag({
                                id: option.value,
                                label: option.label,
                                description: option.description,
                                meta: {
                                    color: option.meta?.color || '#000', // Fallback color if meta is missing
                                }
                            });
                        }}
                    >
                        Edit
                    </span>
                )}
                {quickEdit === 'true' && option.value && (
                    <input
                        type="checkbox"
                        checked={selectedTags.includes(option.value)}
                        onChange={() => handleTagSelect(option.value)}
                    />
                )}
            </Button>
        ))}
    </div>
</PanelBody>


<PanelBody 
    className='bulk-panel'
    title={
        <span>
            <FontAwesomeIcon icon={faEdit} /> Bulk Actions
            <ToggleControl
                checked={quickEdit === 'true'}
                onChange={(value) => setQuickEdit(value ? 'true' : 'off')}
                style={{ marginLeft: '10px' }}
            />
        </span>
    }
    initialOpen={false}
    onToggle={(open) => setQuickEdit(open ? 'true' : 'off')} // Automatically enable "Quick Edit" when panel is opened
>
    {quickEdit === 'true' && (
        <div style={{ marginTop: '10px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
            <Button isDestructive isPrimary onClick={handleBulkDelete}>
                Delete Selected
            </Button>
            
            <Select
                isMulti
                options={tagOptions}
                value={selectedTags}
                onChange={(selectedOptions) => {
                    const tagIds = selectedOptions.map(option => option.value);
                    handleTagChange(choice, tagIds);
                }}
                placeholder="Select or create tags..."
            />
        </div>
    )}
</PanelBody>


        </div>
    );
};

SideBar.propTypes = {
    handleSortChange: PropTypes.func.isRequired,
    sortBy: PropTypes.string.isRequired,
    fieldTypeFilter: PropTypes.array.isRequired,
    setFieldTypeFilter: PropTypes.func.isRequired,
    tagFilter: PropTypes.array.isRequired,
    setTagFilter: PropTypes.func.isRequired,
    tags: PropTypes.object, // Update to be optional
    groupBy: PropTypes.string.isRequired,
    handleGroupByChange: PropTypes.func.isRequired,
    handleSearchChange: PropTypes.func.isRequired,
    searchQuery: PropTypes.string.isRequired,
    fields: PropTypes.arrayOf(
        PropTypes.shape({
            name: PropTypes.string.isRequired,
            type: PropTypes.string.isRequired,
            edited: PropTypes.string,
            created: PropTypes.string,
        })
    ).isRequired,
    formatRelativeTime: PropTypes.func.isRequired,
    handleEditTag: PropTypes.func.isRequired // Add handleEditTag prop type
};

export default SideBar;
