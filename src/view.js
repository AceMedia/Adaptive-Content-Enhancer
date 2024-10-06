import { useEffect, useState } from '@wordpress/element';
import { createElement } from '@wordpress/element';
import { createRoot } from 'react-dom/client';

const renderDropdownBlock = ({ selectedGroup, dropdownValue, dropdownLabel, siteUrl, postId }) => {
    const [selectedLabel, setSelectedLabel] = useState(dropdownLabel);
    const [dropdownGroups, setDropdownGroups] = useState([]);

    useEffect(() => {
        // Construct the URL dynamically
        const apiUrl = `${siteUrl}/wp-json/ace/v1/meta/${postId}`;
        console.log('Fetching dropdown groups from:', apiUrl);

        // Fetch dropdown groups from the REST API
        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                console.log('Fetched dropdown groups:', data);
                setDropdownGroups(data);
            })
            .catch(error => console.error('Error fetching dropdown groups:', error));
    }, [siteUrl, postId]);

    useEffect(() => {
        if (!selectedLabel && selectedGroup && dropdownValue) {
            const group = dropdownGroups.find(group => group.name === selectedGroup);
            if (group) {
                const option = group.options.find(option => option.value === dropdownValue);
                if (option) {
                    setSelectedLabel(option.label);
                }
            }
        }
    }, [selectedGroup, dropdownValue, dropdownGroups, selectedLabel]);

    if (!selectedGroup || !dropdownValue) {
        return createElement(
            'div',
            { className: 'ace-dropdown-block' },
            createElement('p', null, 'No valid selection was made for this block.')
        );
    }

    return createElement(
        'div',
        { className: 'ace-dropdown-block' },
        createElement('p', null, `${selectedLabel || dropdownValue}`)
    );
};

const blocks = document.querySelectorAll('.wp-block-ace-field-options-dropdown-block');

blocks.forEach((block) => {
    const attributesString = block.getAttribute('data-attributes');

    if (!attributesString) {
        console.error("No data-attributes found for this block.");
        return;
    }

    try {
        const attributes = JSON.parse(attributesString);
        console.log('Block attributes:', attributes);

        const root = createRoot(block);
        root.render(createElement(renderDropdownBlock, attributes));
    } catch (error) {
        console.error("Error parsing block attributes:", error);
    }
});