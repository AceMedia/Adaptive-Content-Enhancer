import { Card, CardHeader, CardBody, Button, Flex, FlexItem, Tooltip, Icon } from '@wordpress/components';
import ChoiceFields from './ChoiceFields';
import PropTypes from 'prop-types';

const ChoiceCards = ({
    choices,
    groupBy,
    newlyAddedChoice,
    openIndexes,
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
}) => {
    // Group choices based on groupBy prop
    const groupedChoices = choices.reduce((acc, choice) => {
        if (groupBy === 'tag') {
            if (!choice.tags?.length) {
                if (!acc['Untagged']) acc['Untagged'] = [];
                acc['Untagged'].push(choice);
            } else {
                choice.tags.forEach(tag => {
                    if (!acc[tag]) acc[tag] = [];
                    acc[tag].push(choice);
                });
            }
        } else {
            // group by type
            const key = choice.field_type || 'Unknown Type';
            if (!acc[key]) acc[key] = [];
            acc[key].push(choice);
        }
        return acc;
    }, {});

    return (
        <div>
            {Object.entries(groupedChoices).map(([group, groupChoices]) => (
                <div key={group} className="choice-group">
                    <h3 className="group-title">{group}</h3>
                    {groupChoices.map((choice, index) => (
                        <Card
                            key={`${group}-${index}`}
                            style={{
                                width: '100%',
                                marginBottom: '1em',
                                border: newlyAddedChoice === choice.name ? '2px solid #007cba' : 'none',
                                transition: 'border 0.3s',
                            }}
                            className={newlyAddedChoice === choice.name ? 'highlight' : ''}
                        >
                            <CardHeader onClick={() => toggleOpen(index)}>
                                <Flex justify="space-between" align="center" gap={2}>
                                    <FlexItem>
                                        <Flex align="center" gap={2} style={{ textAlign: 'left' }}>
                                            <Icon
                                                icon={
                                                    choice.field_type === 'text'
                                                        ? 'menu'
                                                        : choice.field_type === 'select'
                                                        ? 'menu'
                                                        : choice.field_type === 'checkbox'
                                                        ? 'yes-alt'
                                                        : choice.field_type === 'truefalse'
                                                        ? 'admin-site-alt3'
                                                        : choice.field_type === 'radio'
                                                        ? 'radio'
                                                        : 'admin-tools'
                                                }
                                            />
                                            <h2>{choice.title || `Group ${index + 1}`}</h2>
                                        </Flex>
                                    </FlexItem>

                                    <Flex align="center" gap={3} style={{ textAlign: 'right', width: 'auto' }}>
                                        {choice.tags && choice.tags.length > 0 && (
                                            <Flex align="left" wrap="nowrap" gap={1} style={{ textAlign: 'left', width: 'auto' }}>
                                                {choice.tags.map((tag, idx) => (
                                                    <span
                                                        key={idx}
                                                        style={{
                                                            backgroundColor: '#007cba',
                                                            color: '#fff',
                                                            padding: '2px 6px',
                                                            borderRadius: '12px',
                                                            fontSize: '0.75em',
                                                            whiteSpace: 'nowrap',
                                                        }}
                                                    >
                                                        {tag}
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

                            {openIndexes[index] && (
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
                                    />
                                    
                                    <Button variant="secondary" isDestructive onClick={() => openConfirmDialog(index, choice.name)}>
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
};

ChoiceCards.propTypes = {
    groupBy: PropTypes.oneOf(['type', 'tag']).isRequired,
    // ... other propTypes
};

export default ChoiceCards;
