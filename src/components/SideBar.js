import { 
    SelectControl, 
    __experimentalToggleGroupControl as ToggleGroupControl,
    __experimentalToggleGroupControlOption as ToggleGroupControlOption 
} from '@wordpress/components';
import PropTypes from 'prop-types';

const SideBar = ({ 
    handleSortChange, 
    sortBy, 
    fieldTypeFilter, 
    handleFieldTypeChange 
}) => {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
            <div>
                <div style={{ marginBottom: '8px' }}>Sort by:</div>
                
                <ToggleGroupControl
                    label="Sort by"
                    value="created"
                    onChange={handleSortChange}
                    isBlock
                    __nextHasNoMarginBottom
                    defaultChecked="created"
                >
                    <ToggleGroupControlOption 
                        value="created" 
                        label="Date Created" 
                    />
                    <ToggleGroupControlOption 
                        value="edited" 
                        label="Date Edited" 
                    />
                    <ToggleGroupControlOption 
                        value="title" 
                        label="Alphabetical" 
                    />
                </ToggleGroupControl>
            </div>

            <SelectControl
                label="Filter by Field Type"
                value={fieldTypeFilter}
                options={[
                    { label: 'All Types', value: '' },
                    { label: 'Text', value: 'text' },
                    { label: 'Select', value: 'select' },
                    { label: 'Checkbox', value: 'checkbox' },
                    { label: 'True/False', value: 'truefalse' },
                    { label: 'Radio', value: 'radio' },
                    { label: 'Buttons', value: 'buttons' }
                ]}
                onChange={handleFieldTypeChange}
            />
        </div>
    );
};

SideBar.propTypes = {
    handleSortChange: PropTypes.func.isRequired,
    sortBy: PropTypes.string.isRequired,
    fieldTypeFilter: PropTypes.string.isRequired,
    handleFieldTypeChange: PropTypes.func.isRequired,
};

export default SideBar;
