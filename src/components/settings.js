import React, { useState, useEffect } from 'react';
import { render } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';


function createLowercaseUnderscored(string) {
    return string.toLowerCase().replace(/\s+/g, '_');
}

const AdaptiveContentEnhancer = () => {
    const [choices, setChoices] = useState([]);
    const [trueFalseFields, setTrueFalseFields] = useState([]);
    const [editKey, setEditKey] = useState(null);

    useEffect(() => {
        // Fetch options data from the backend
        apiFetch({ path: '/ace/v1/get-choices' })
            .then(setChoices)
            .catch((error) => console.error('Error fetching choices:', error));

        // Fetch true/false fields from the backend
        apiFetch({ path: '/ace/v1/get-truefalse-fields' })
            .then(setTrueFalseFields)
            .catch((error) => console.error('Error fetching true/false fields:', error));
    }, []);

    const handleSave = () => {
        // Create payload for saving choices and true/false fields
        const payload = {
            choices,
            true_false_fields: trueFalseFields,
            action: 'save_choices',
            _wpnonce: aceAjax.nonce, // Assuming nonce is localized
        };

        // Save settings via AJAX
        apiFetch({
            path: '/wp-admin/admin-post.php',
            method: 'POST',
            data: payload,
        }).then((response) => {
            alert('Settings saved successfully!');
        }).catch((error) => {
            console.error('Error saving settings:', error);
            alert('Failed to save settings. Please try again.');
        });
    };

    const handleAddChoiceGroup = () => {
        setChoices([
            ...choices,
            {
                name: '',
                field_type: 'select',
                description: '',
                options: [],
                show_in_rest: false,
            },
        ]);
    };

    const handleAddTrueFalseField = () => {
        setTrueFalseFields([
            ...trueFalseFields,
            {
                name: '',
                true_label: 'True',
                false_label: 'False',
                show_in_rest: false,
            },
        ]);
    };

    const confirmAndRemoveGroup = (index, fieldType, groupName) => {
        if (window.confirm('Are you sure you want to remove this group?')) {
            // Convert groupName to lowercase underscored format
            const processedGroupName = createLowercaseUnderscored(groupName);
            const optionName = fieldType === 'truefalse' ? 'truefalse_' + processedGroupName : 'choices_' + processedGroupName;
    
            apiFetch({
                path: '/ace/v1/delete-option',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', // Ensure proper JSON content type
                },
                body: JSON.stringify({
                    option_name: optionName,
                    _wpnonce: aceAjax.nonce, // Pass the nonce here as well
                }),
            }).then((response) => {
                if (response.success) {
                    alert('Group removed successfully!');
                    if (fieldType === 'truefalse') {
                        setTrueFalseFields(trueFalseFields.filter((_, i) => i !== index));
                    } else {
                        setChoices(choices.filter((_, i) => i !== index));
                    }
                } else {
                    alert('Failed to remove group. Please try again.');
                    console.error('Error:', response.error); // Log the exact error for debugging
                }
            }).catch((error) => {
                console.error('Error removing group:', error);
                alert('An error occurred. Please try again.');
            });
        }
    };
    
    
    

    const renderChoicesTable = () => (
        <div>
            <h2>Option Groups</h2>
            <table className="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Group Name</th>
                        <th>Field Type</th>
                        <th>Description</th>
                        <th>Options</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    {choices.map((choice, index) => (
                        <tr key={index}>
                            <td><input type="text" value={choice.name} onChange={(e) => updateChoice(index, 'name', e.target.value)} /></td>
                            <td>
                                <select value={choice.field_type} onChange={(e) => updateChoice(index, 'field_type', e.target.value)}>
                                    <option value="select">Select</option>
                                    <option value="checkbox">Checkbox</option>
                                    <option value="radio">Radio</option>
                                    <option value="buttons">Buttons</option>
                                </select>
                            </td>
                            <td><textarea value={choice.description} onChange={(e) => updateChoice(index, 'description', e.target.value)} /></td>
                            <td>
                                                                {/* Placeholder for handling options dynamically */}
                                                                <button onClick={() => editChoiceOptions(index)}>Edit Options</button>
                            </td>
                            <td>
                                <button onClick={() => confirmAndRemoveGroup(index, choice.field_type, choice.name)}>Remove</button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <button onClick={handleAddChoiceGroup}>Add Group</button>
        </div>
    );

    const renderTrueFalseFieldsTable = () => (
        <div>
            <h2>True/False Fields</h2>
            <table className="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Field Name</th>
                        <th>Label for True</th>
                        <th>Label for False</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    {trueFalseFields.map((field, index) => (
                        <tr key={index}>
                            <td><input type="text" value={field.name} onChange={(e) => updateTrueFalseField(index, 'name', e.target.value)} /></td>
                            <td><input type="text" value={field.true_label} onChange={(e) => updateTrueFalseField(index, 'true_label', e.target.value)} /></td>
                            <td><input type="text" value={field.false_label} onChange={(e) => updateTrueFalseField(index, 'false_label', e.target.value)} /></td>
                            <td>
                                <button onClick={() => confirmAndRemoveGroup(index, 'truefalse', field.name)}>Remove</button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <button onClick={handleAddTrueFalseField}>Add True/False Field</button>
        </div>
    );

    return (
        <div className="wrap">
            <h1>Adaptive Content Enhancer Settings</h1>
            {renderChoicesTable()}
            {renderTrueFalseFieldsTable()}
            <button className="button-primary" onClick={handleSave}>Save Settings</button>
        </div>
    );
};

const updateChoice = (index, field, value) => {
    const updatedChoices = [...choices];
    updatedChoices[index][field] = value;
    setChoices(updatedChoices);
};

const updateTrueFalseField = (index, field, value) => {
    const updatedFields = [...trueFalseFields];
    updatedFields[index][field] = value;
    setTrueFalseFields(updatedFields);
};

// Render the component to the admin settings page
document.addEventListener('DOMContentLoaded', () => {
    const settingsElement = document.getElementById('adaptive-content-enhancer-settings');
    if (settingsElement) {
        render(<AdaptiveContentEnhancer />, settingsElement);
    }
});

export default AdaptiveContentEnhancer;