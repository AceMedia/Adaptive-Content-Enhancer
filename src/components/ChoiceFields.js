import { TextControl, SelectControl, TextareaControl, ToggleControl, Button } from '@wordpress/components';

                const ChoiceFields = ({ 
                    choice, 
                    handleTextChange, 
                    handleToggleChange, 
                    handleTagChange, 
                    updateOption, 
                    removeOption, 
                    addOption, 
                    isModalOpen 
                }) => (
                    <>
                        <TextControl
                        label="Group Title"
                        value={choice.title}
                        onChange={(newValue) => handleTextChange(choice, 'title', newValue)}
                    />
            
            <TextControl
                label="Group Name"
                value={choice.name}
                disabled={!isModalOpen && !choice.isNew} // Only disable when not in modal and not a new group
                onChange={(newValue) => {
                    const formattedValue = createLowercaseUnderscored(newValue);
                    handleTextChange(choice, 'name', formattedValue);
                    setNewChoice((prevNewChoice) => ({
                        ...prevNewChoice,
                        name: formattedValue,
                    }));
                }} // Allow manual updates and apply formatting
            />
            
                        <SelectControl
                            label="Field Type"
                            value={choice.field_type}
                            options={[
                                { label: 'Text', value: 'text' },
                                { label: 'Select', value: 'select' },
                                { label: 'Checkbox', value: 'checkbox' },
                                { label: 'True/False', value: 'truefalse' },
                                { label: 'Radio', value: 'radio' },
                                { label: 'Buttons', value: 'buttons' },
                            ]}
                            onChange={(newValue) => handleTextChange(choice, 'field_type', newValue)} // Update choice's field_type directly
                        />
                        <TextareaControl
                            label="Description"
                            value={choice.description}
                            onChange={(newValue) => handleTextChange(choice, 'description', newValue)}
                        />
                        <ToggleControl
                label="Show in REST API"
                checked={choice.show_in_rest}
                onChange={(newValue) => handleToggleChange(choice, newValue)}
            />
            
            
                    <TextControl
                        label="Tags (comma-separated)"
                        value={choice.tags ? choice.tags.join(', ') : ''}
                        onChange={(newValue) => handleTagChange(choice, newValue.split(',').map(tag => tag.trim()))}
                    />
            
            
            
            
                    {/* Display tags and descriptions */}
                    {choice.tags_with_descriptions && choice.tags_with_descriptions.length > 0 && (
                        <div style={{ marginTop: '16px' }}>
                            <strong>Tags with Descriptions:</strong>
                            <ul>
                                {choice.tags_with_descriptions.map((tag, index) => (
                                    <li key={index}>
                                        <strong>{tag.name}:</strong> {tag.description}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
            
            {choice.field_type !== 'text' && (
            <div style={{ marginTop: '16px' }}>
                        <strong>Options:</strong>
                        {choice.options.map((option, optionIndex) => (
                            <div key={optionIndex} style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <TextControl
                                    placeholder="Option Label"
                                    value={option.label}
                                    onChange={(value) => updateOption(choice, option, 'label', value)}
                                />
                                <TextControl
                placeholder="Option Value"
                value={option.value}
                disabled={choice.field_type === 'truefalse' || !!option.disabled} // Ensure the option is disabled if marked or for true/false
                    onChange={(value) => updateOption(choice, option, 'value', value)}
            />
            
            {choice.field_type !== 'truefalse' && (
                                <Button
                                    isDestructive
                                    onClick={() => removeOption(choice, option)}
                                    disabled={choice.field_type === 'truefalse'} // Prevent removal of true/false options
                                >
                                    Remove
                                </Button>
                        )}
                            </div>
                        ))}
                        {choice.field_type !== 'truefalse' && (
                            <Button 
                                isPrimary 
                                onClick={() => {
                                    const option = isModalOpen ? null : choice;
                                    addOption(option);
                                }}
                            >
                                Add Option
                            </Button>
                        )}
                    </div>
            
            )}
            
            
                    </>
                );

                export default ChoiceFields;