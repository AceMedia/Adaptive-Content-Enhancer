import { 
    __experimentalToggleGroupControl as ToggleGroupControl,
    __experimentalToggleGroupControlOption as ToggleGroupControlOption,
    PanelBody,
    Button,
    TextControl
} from '@wordpress/components';
import PropTypes from 'prop-types';
import { useState, useEffect } from '@wordpress/element';
import { Icon, filter, info, formatCapitalize } from '@wordpress/icons';

const SearchComponent = ({ searchQuery, handleSearchChange }) => {
    return (
        <input
            type="text"
            value={searchQuery}
            onChange={(e) => handleSearchChange(e.target.value)}
            placeholder="Search..."
            style={{ width: '100%', padding: '5px', marginBottom: '10px' }}
        />
    );
};


const FieldSettingsOverview = ({ fields, formatRelativeTime, groupBy }) => {

 


    const [isOpen, setIsOpen] = useState(false);
    const [recentFields, setRecentFields] = useState([]);

    // Update recentFields based on sorting
    const updateRecentFields = () => {
        const updatedRecentFields = fields
            .filter((field) => field.edited || field.created)
            .sort((a, b) => new Date(b.edited || b.created) - new Date(a.edited || a.created))
            .slice(0, 5);
        setRecentFields(updatedRecentFields);
    };

    useEffect(() => {
        if (isOpen) {
            updateRecentFields();
        }
    }, [isOpen, fields]); // Depend on `isOpen` and `fields` to update when needed

    return (
        <PanelBody 
            title="Overview" 
            initialOpen={false} 
            onToggle={() => setIsOpen((prev) => !prev)} 
            icon={info}
        >
            <div style={{ marginBottom: '15px' }}>
                <strong>Total Fields:</strong> {fields.length}
                <ul style={{ listStyle: 'none', padding: 0, marginTop: '5px' }}>
                    {Object.entries(fields.reduce((acc, field) => {
                        acc[field.field_type] = (acc[field.field_type] || 0) + 1;
                        return acc;
                    }, {})).map(([type, count]) => (
                        <li key={type} style={{ fontSize: '0.9em' }}>
                            {type.charAt(0).toUpperCase() + type.slice(1)}: {count}
                        </li>
                    ))}
                </ul>
            </div>


            <div>
                <strong>Recently Modified:</strong>
                <ul style={{ listStyle: 'none', padding: 0, marginTop: '5px' }}>
                    {recentFields.map((field) => {
                        const dateToFormat = field.edited || field.created;
                        const formattedDate = formatRelativeTime(dateToFormat);
                        return (
                            <li key={field.name} style={{ fontSize: '0.9em', marginBottom: '5px' }}>
                                {field.name} - {formattedDate || 'No date available'}
                            </li>
                        );
                    })}
                </ul>
            </div>
        </PanelBody>
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

            <PanelBody icon={filter} title="Filter by Field Type" initialOpen={true}>
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
                        {option.label}
                    </Button>
                ))}
            </div>
        </PanelBody>


        <PanelBody icon={filter} title="Filter by Tags" initialOpen={true}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '5px' }}>
                    {[
                        { label: 'All Tags', value: '' },
                        ...Object.keys(tags).map(tag => ({ label: tag, value: tag }))
                    ].map((option) => (
                        <Button
                            key={option.value}
                            isPressed={tagFilter.includes(option.value)}
                            onClick={() => handleTagChange(option.value)}
                            className="toggle-button"
                        >
                            {option.label}
                        </Button>
                    ))}
                </div>
            </PanelBody>

            <PanelBody icon={filter} title="Group by" initialOpen={false}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '5px' }}>
                    {[
                        { label: 'Nothing', value: 'nothing' },
                        { label: 'Type', value: 'type' },
                        { label: 'Tag', value: 'tag' }
                    ].map((option) => (
                        <Button
                            key={option.value}
                            isPressed={groupBy === option.value}
                            onClick={() => handleGroupByChange(option.value)}
                            className="toggle-button"
                        >
                            {option.label}
                        </Button>
                    ))}
                </div>
            </PanelBody>

            <FieldSettingsOverview 
                fields={fields} 
                formatRelativeTime={formatRelativeTime}
            />
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
};


export default SideBar;
