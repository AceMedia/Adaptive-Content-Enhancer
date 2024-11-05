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


    const [showAllTags, setShowAllTags] = useState(false);

    const displayedTags = showAllTags ? Object.keys(tags) : Object.keys(tags).slice(0, 10);
    


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

    function hexToRgba(hex, alpha = 1) {
        hex = hex.replace(/^#/, '');
    
        let r = parseInt(hex.substring(0, 2), 16);
        let g = parseInt(hex.substring(2, 4), 16);
        let b = parseInt(hex.substring(4, 6), 16);
    
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }


    const darkenColor = (hex, amount = 0.2) => {
        const [r, g, b] = hex.match(/\w\w/g).map(x => parseInt(x, 16));
        return `rgba(${Math.max(0, r - r * amount)}, ${Math.max(0, g - g * amount)}, ${Math.max(0, b - b * amount)}, 1)`;
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
            ...displayedTags.map(tagId => ({
                label: tags[tagId].name,
                value: tagId,
                description: tags[tagId].description,
                meta: {
                    color: tags[tagId].meta?.color || '#000'
                }
            }))
        ].map((option) => (
            <Button
            key={option.value}
            isPressed={quickEdit !== 'true' && tagFilter.includes(option.value)}
            onClick={() => {
                if (quickEdit === 'true') {
                    handleTagSelect(option.value);
                } else {
                    handleTagChange(option.value);
                }
            }}
            className="toggle-button"
            style={{
                color: option.meta?.color || '#000',
                backgroundColor: quickEdit !== 'true' && tagFilter.includes(option.value)
                    ? hexToRgba(`${option.meta?.color || '#000000'}`, 0.5)  // Darker background for active tags
                    : hexToRgba(`${option.meta?.color || '#000000'}`, 0.25)    // Lighter background for inactive tags
            }}
        >
            <span
                className="field-tag-color"
                style={{
                    background: option.meta?.color
                }}
            ></span>
            {option.label}
        
            {/* Show Edit button for each tag */}
            {quickEdit !== 'true' && option.value && (
                <span
                    className="field-tag-count"
                    onClick={(e) => {
                        e.stopPropagation();
                        handleEditTag({
                            id: option.value,
                            label: option.label,
                            description: option.description,
                            meta: {
                                color: option.meta?.color || '#000',
                            }
                        });
                    }}
                >
                    Edit
                </span>
            )}
        
            {quickEdit === 'true' && option.value && (
                <ToggleControl
                    checked={selectedTags.includes(option.value)}
                    onChange={() => handleTagSelect(option.value)}
                    style={{ marginLeft: '10px' }}
                    className="tag-toggle"
                />
            )}
        </Button>
        
        ))}

        {/* Show More / Show Less button */}
        {Object.keys(tags).length > 10 && (
            <Button
                onClick={() => setShowAllTags(prevState => !prevState)}
                className="toggle-button"
            >
                {showAllTags ? 'Show Less' : 'Show More'}
            </Button>
        )}
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
