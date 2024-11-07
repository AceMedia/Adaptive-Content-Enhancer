import { TextControl, SelectControl, TextareaControl, ToggleControl, Button, Flex, FlexBlock } from '@wordpress/components';
import Creatable from 'react-select/creatable';


import apiFetch from '@wordpress/api-fetch';


const ChoiceFields = ({ 
    choice, 
    handleTextChange, 
    handleToggleChange, 
    handleTagChange, 
    updateOption, 
    removeOption, 
    addOption, 
    isModalOpen,
    setNewChoice,
    currentTag,
    tags, // Receive the tags object
    setTags,
    getRandomColor,
    customStyles,
}) => {
    // Convert tags object to options for react-select
    const tagOptions = Object.keys(tags).map(tagId => ({
        value: tagId,
        label: tags[tagId].name,
        meta: { color: tags[tagId].meta.color } // Ensure color is passed here
    }));


    function createLowercaseUnderscored(string) {
        return string.toLowerCase().replace(/\s+/g, '_');
    }

    // Convert tag IDs to react-select format, skip if unknown
    const selectedTags = choice.tags ? choice.tags
        .filter(tagId => tags[tagId]) // Filter out unknown tags
        .map(tagId => ({
            value: tagId,
            label: tags[tagId].name,
            meta: { color: tags[tagId].meta.color }
        })) : [];

    return (
        <>
             <Flex>
            <FlexBlock style={{ width: '50%' }}>
                <TextControl
                    label="Title"
                    value={choice.title}
                    onChange={(newValue) => handleTextChange(choice, 'title', newValue)}
                                __next40pxDefaultSize='true'
                />
            </FlexBlock>
            <FlexBlock style={{ width: '50%' }}>
                <TextControl
                    label="ID"
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
                                __next40pxDefaultSize='true'
                />
            </FlexBlock>
        </Flex>

            <SelectControl
                label="Field Type"
                value={choice.field_type}
                options={[
                    { label: 'Text', value: 'text' },
                    { label: 'Select', value: 'select' },
                    { label: 'Checkbox', value: 'checkbox' },
                    { label: 'True/False', value: 'truefalse' },
                    { label: 'Radio', value: 'radio' }
                ]}
                onChange={(newValue) => handleTextChange(choice, 'field_type', newValue)} // Update choice's field_type directly
                __next40pxDefaultSize='true'
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




<label htmlFor="tag-set">Select or Create Tags</label>
            <Creatable
                isMulti
                id='tag-set'
                options={tagOptions}
                value={selectedTags}
                className='ace-tag-select'                
                styles={customStyles} // Apply custom styles to display tag color
                onChange={(selectedOptions, actionMeta) => {
                    if (actionMeta.action === 'create-option') {
                        const temporaryTagId = `temp-${Date.now()}`;
                        const randomColor = getRandomColor();
                        const newTag = { name: actionMeta.option.label, description: '', meta: { color: randomColor } };
                        const temporaryTag = { value: temporaryTagId, label: newTag.name, meta: { color: randomColor } };

                        // Optimistically update tag options and selected tags
                        const updatedTagOptions = [...tagOptions, temporaryTag];
                        const updatedSelectedTags = [...selectedTags, temporaryTag];
                        setTags((prevTags) => ({
                            ...prevTags,
                            [temporaryTagId]: { name: newTag.name, description: '', meta: { color: randomColor } },
                        }));
                        handleTagChange(choice, updatedSelectedTags.map(tag => tag.value));
                        setNewChoice((prevNewChoice) => ({
                            ...prevNewChoice,
                            tags: updatedSelectedTags.map(tag => tag.value),
                        }));

                        // Proceed with the API call
                        apiFetch({
                            path: '/wp/v2/field_tags',
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(newTag),
                        })
                            .then((response) => {
                                if (response.id) {
                                    const createdTag = { value: response.id, label: response.name, meta: { color: randomColor } };
                                    // Update tags to replace temporary tag with the saved tag
                                    setTags((prevTags) => {
                                        const { [temporaryTagId]: removed, ...restTags } = prevTags;
                                        return {
                                            ...restTags,
                                            [response.id]: { name: response.name, description: '', meta: { color: randomColor } },
                                        };
                                    });
                                    handleTagChange(choice, [...updatedSelectedTags.filter(tag => tag.value !== temporaryTagId), createdTag].map(tag => tag.value));
                                } else {
                                    console.error('Error creating tag:', response);
                                }
                            })
                            .catch((error) => {
                                console.error('Error creating tag:', error);
                                // Optionally handle error by removing the temporary tag
                                setTags((prevTags) => {
                                    const { [temporaryTagId]: removed, ...restTags } = prevTags;
                                    return restTags;
                                });
                            });
                    } else {
                        const tagIds = selectedOptions.map(option => option.value);
                        handleTagChange(choice, tagIds);
                    }
                }}
            />
            

            {choice.field_type !== 'text' && (
                <div style={{ marginTop: '1em' }}>
                    <strong>Field Options:</strong>
                    {choice.options.map((option, optionIndex) => (
                        <div key={optionIndex} style={{ display: 'flex', alignItems: 'center', gap: '1em' }} className='field-options-row'>
                            <TextControl
                                placeholder="Option Label"
                                value={option.label}
                                onChange={(value) => updateOption(choice, option, 'label', value)}
                                __next40pxDefaultSize='true'
                                __nextHasNoMarginBottom
                            />
                            <TextControl
                                placeholder="Option Value"
                                value={option.value}
                                disabled={choice.field_type === 'truefalse' || !!option.disabled} // Ensure the option is disabled if marked or for true/false
                                onChange={(value) => updateOption(choice, option, 'value', value)}
                                __next40pxDefaultSize='true'
                                __nextHasNoMarginBottom
                            />
                            <Button
                                isDestructive
                                onClick={() => removeOption(choice, option)}
                            >
                                Remove
                            </Button>
                        </div>
                    ))}
                    <Button 
                        isPrimary 
                        onClick={() => addOption(choice)}
                    >
                        Add Option
                    </Button>
                </div>
            )}
        </>
    );
};

export default ChoiceFields;


