import { DropdownMenu, MenuGroup, MenuItem, SelectControl } from '@wordpress/components';

const SideBar = ({ handleSortChange, fieldTypeFilter, handleFieldTypeChange }) => {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            <DropdownMenu
                icon="sort"
                label="Sort Options"
                toggleProps={{ isPrimary: true }}
            >
                {({ onClose }) => (
                    <MenuGroup label="Sort by">
                        <MenuItem onClick={() => { handleSortChange('created'); onClose(); }}>Date Created</MenuItem>
                        <MenuItem onClick={() => { handleSortChange('edited'); onClose(); }}>Date Edited</MenuItem>
                        <MenuItem onClick={() => { handleSortChange('title'); onClose(); }}>Alphabetical</MenuItem>
                    </MenuGroup>
                )}
            </DropdownMenu>

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
                onChange={(newValue) => handleFieldTypeChange(newValue)}
            />
        </div>
    );
};

export default SideBar;
