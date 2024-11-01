import { SelectControl, __experimentalRadio as Radio, __experimentalRadioGroup as RadioGroup } from '@wordpress/components';
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
                
                <RadioGroup 
                    label="Sort by"
                    onChange={handleSortChange}
                    checked={sortBy}
                    defaultChecked="created"
                >
                    <Radio 
                        __next40pxDefaultSize 
                        value="created"
                    >
                        Date Created
                    </Radio>
                    <Radio 
                        __next40pxDefaultSize 
                        value="edited"
                    >
                        Date Edited
                    </Radio>
                    <Radio 
                        __next40pxDefaultSize 
                        value="title"
                    >
                        Alphabetical
                    </Radio>
                </RadioGroup>
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
