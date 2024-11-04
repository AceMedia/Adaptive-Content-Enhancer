import { Card, CardHeader, CardBody, Button, Flex, FlexItem, Tooltip } from '@wordpress/components';
import ChoiceFields from './ChoiceFields';
import PropTypes from 'prop-types';
import { useState, useEffect } from '@wordpress/element';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheckSquare, faToggleOn, faCircleDot, faFont, faSquareCaretDown } from '@fortawesome/free-solid-svg-icons';

const ChoiceCards = ({
    choices,
    groupBy,
    newlyAddedChoice,
    openIndexes, // Default to empty array
    toggleOpen,
    handleTextChange,
    handleToggleChange,
    handleTagChange,
    updateOption,
    removeOption,
    addOption,
    isModalOpen,
    openConfirmDialog,
    formatRelativeTime,
    sortBy = 'edited',
    tags,
    quickEdit,
    setQuickEdit,
    selectedChoices,
    handleChoiceSelect,
    onAddCard,
    handleAddChoiceGroup,
}) => {

    const fieldTypeIcons = {
        text: faFont,
        checkbox: faCheckSquare,
        select: faSquareCaretDown,
        radio: faCircleDot,
        truefalse: faToggleOn,
    };

    // Display a message and add button if there are no choices
    if (choices.length === 0) {
        return (
            <div className="no-choices">
                <p>No fields match the current filters.</p>
                <Button isPrimary variant="secondary" onClick={handleAddChoiceGroup} icon="plus">
                    Add New Field
                </Button>
            </div>
        );
    }

    // Group choices by the specified criteria
    const groupedChoices = choices.reduce((acc, choice) => {
        if (groupBy === 'nothing') {
            const key = 'all';
            if (!acc[key]) acc[key] = [];
            acc[key].push(choice);
        } else if (groupBy === 'tag') {
            if (!choice.tags?.length) {
                const key = 'Untagged';
                if (!acc[key]) acc[key] = [];
                acc[key].push(choice);
            } else {
                choice.tags.forEach(tagId => {
                    if (!acc[tagId]) acc[tagId] = [];
                    acc[tagId].push(choice);
                });
            }
        } else {
            const key = choice.field_type || 'Unknown Type';
            if (!acc[key]) acc[key] = [];
            acc[key].push(choice);
        }
        return acc;
    }, {});

    // Apply sorting within each group based on the `sortBy` criteria
    Object.keys(groupedChoices).forEach(groupKey => {
        groupedChoices[groupKey].sort((a, b) => {
            switch (sortBy) {
                case 'edited':
                    return new Date(b.edited) - new Date(a.edited);
                case 'created':
                    return new Date(b.created) - new Date(a.created);
                case 'title':
                    return a.name.localeCompare(b.title);
                default:
                    return new Date(b.edited) - new Date(a.edited);
            }
        });
    });

    // Map tag IDs to tag names for display
    const displayGroupName = (group) => {
        if (groupBy === 'tag') {
            return tags[group]?.name || 'Unknown Tag';
        }
        return group;
    };



    if (choices.length != 0) {
    return (
        <div>
            {Object.entries(groupedChoices).map(([group, groupChoices]) => (
                <div key={group} className="choice-group">
                    {group !== 'all' && (
                        <h3 className="group-title">
                            {displayGroupName(group)}
                        </h3>
                    )}
                    {groupChoices.map(choice => (
                        <Card
                            key={choice.name}
                            id={choice.name}
                            data-group-id={group} // Pass the group ID as a data attribute
                            style={{
                                width: '100%',
                                marginBottom: '1em',
                                border: newlyAddedChoice === choice.name ? '2px solid #007cba' : 'none',
                                transition: 'border 0.3s',
                            }}
                            className={newlyAddedChoice === choice.name ? 'highlight' : 'test'}
                        >
                            <CardHeader onClick={() => toggleOpen(choice.name, group)}>
                                <Flex justify="space-between" align="center" gap={2}>
                                    <FlexItem>
                                        <Flex align="center" gap={2} style={{ textAlign: 'left' }}>
                                            <span className='icon'>
                                                <FontAwesomeIcon icon={fieldTypeIcons[choice.field_type] || fieldTypeIcons.default} />
                                            </span>
                                            <h2>{choice.title || `Group ${index + 1}`}</h2>

                                            {quickEdit === 'true' && (
                                                <input
                                                    type="checkbox"
                                                    checked={selectedChoices.includes(choice)}
                                                    onChange={() => { console.log(choice); handleChoiceSelect(choice); }}
                                                />
                                            )}
                                        </Flex>
                                    </FlexItem>
                                    <Flex align="center" gap={3} style={{ textAlign: 'right', width: 'auto' }}>
                                        {choice.tags && choice.tags.length > 0 && (
                                            <Flex align="left" wrap="nowrap" gap={1} style={{ textAlign: 'left', width: 'auto' }}>
                                                {choice.tags.map((tagId, idx) => (
                                                    <span
                                                        key={idx}
                                                        style={{
                                                            backgroundColor: tags[tagId]?.meta?.color?.[0] || '#007cba', // Use the color value from the meta object
                                                            color: '#fff',
                                                            padding: '2px 6px',
                                                            borderRadius: '12px',
                                                            fontSize: '0.75em',
                                                            whiteSpace: 'nowrap',
                                                        }}
                                                    >
                                                        {tags[tagId] ? tags[tagId].name : 'Unknown Tag'}
                                                    </span>
                                                ))}
                                            </Flex>
                                        )}
                                        {choice.created && (
                                            <div style={{ fontSize: '0.85em', color: '#777', display: 'flex', gap: '10px' }}>
                                                <Tooltip text={`Created at ${new Date(choice.created).toLocaleString()}`}>
                                                    <p><strong>Created:</strong> {formatRelativeTime(choice.created)}</p>
                                                </Tooltip>
                                                <Tooltip text={`Last edited at ${new Date(choice.edited).toLocaleString()}`}>
                                                    <p><strong>Updated:</strong> {formatRelativeTime(choice.edited)}</p>
                                                </Tooltip>
                                            </div>
                                        )}
                                    </Flex>
                                </Flex>
                            </CardHeader>
                            {openIndexes.includes(`${groupBy}-${choice.name}-${group}`) && (
                                <CardBody>
                                    <ChoiceFields
                                        choice={choice}
                                        handleTextChange={handleTextChange}
                                        handleToggleChange={handleToggleChange}
                                        handleTagChange={handleTagChange}
                                        updateOption={updateOption}
                                        removeOption={removeOption}
                                        addOption={addOption}
                                        isModalOpen={isModalOpen}
                                        tags={tags}
                                    />
                                    <Button variant="secondary" isDestructive onClick={() => openConfirmDialog(choice.name)}>
                                        Delete Field
                                    </Button>
                                </CardBody>
                            )}
                        </Card>
                    ))}
                </div>
            ))}
        </div>
    );
    }
};

// Add PropTypes validation
ChoiceCards.propTypes = {
    choices: PropTypes.arrayOf(PropTypes.shape({
        id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
        name: PropTypes.string.isRequired,
    })).isRequired,
    groupBy: PropTypes.string,
    newlyAddedChoice: PropTypes.string,
    openIndexes: PropTypes.array,
    toggleOpen: PropTypes.func.isRequired,
    onAddCard: PropTypes.func.isRequired // Add prop type for onAddCard
};

export default ChoiceCards;
