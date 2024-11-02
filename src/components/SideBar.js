import { 
    __experimentalToggleGroupControl as ToggleGroupControl,
    __experimentalToggleGroupControlOption as ToggleGroupControlOption,
    PanelBody,
    Button,
    TextControl
} from '@wordpress/components';
import PropTypes from 'prop-types';
import { useState, useEffect } from '@wordpress/element';
import { Icon, filter, info } from '@wordpress/icons';

const SearchComponent = ({ searchQuery, handleSearchChange }) => {
    return (
        <TextControl
            label="Search Fields"
            value={searchQuery}
            onChange={handleSearchChange}
            placeholder="Type to search..."
        />
    );
};

const FieldSettingsOverview = ({ fields, formatRelativeTime, groupBy }) => {
    console.log('FieldSettingsOverview props:', {
        fieldsCount: fields.length,
        groupBy,
        sampleField: fields[0]
    });

 


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
    handleFieldTypeChange,
    groupBy,
    handleGroupByChange,
    searchQuery,
    handleSearchChange,
    fields,
    formatRelativeTime
}) => {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
            <SearchComponent 
                searchQuery={searchQuery} 
                handleSearchChange={handleSearchChange} 
            />

            <ToggleGroupControl
                label="Sort by"
                value='edited'
                onChange={handleSortChange}
                defaultChecked="created"
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
                        { label: 'Radio', value: 'radio' },
                        { label: 'Buttons', value: 'buttons' }
                    ].map((option) => (
                        <Button
                            key={option.value}
                            isPressed={fieldTypeFilter === option.value}
                            onClick={() => handleFieldTypeChange(option.value)}
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
    fieldTypeFilter: PropTypes.string.isRequired,
    handleFieldTypeChange: PropTypes.func.isRequired,
    groupBy: PropTypes.string.isRequired,
    handleGroupByChange: PropTypes.func.isRequired,
    searchQuery: PropTypes.string.isRequired,
    handleSearchChange: PropTypes.func.isRequired,
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
