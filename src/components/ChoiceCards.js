import { Card, CardHeader, CardBody, Button, Flex, FlexItem, Tooltip, Icon } from '@wordpress/components';
import ChoiceFields from './ChoiceFields';
import PropTypes from 'prop-types';

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
}) => {

    console.log('ChoiceCards received sortBy:', sortBy); // Debug statement

    // Sort choices by edited date and then group them
    const sortedChoices = [...choices].sort((a, b) => {
        switch(sortBy) {
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

    // Keep existing grouping logic
    const groupedChoices = sortedChoices.reduce((acc, choice) => {
        const key = groupBy === 'nothing' ? 'all' : 
                    groupBy === 'tag' ? (!choice.tags?.length ? 'Untagged' : choice.tags[0]) :
                    choice.field_type || 'Unknown Type';
        
        if (!acc[key]) acc[key] = [];
        acc[key].push(choice);
        return acc;
    }, {});

    return (
        <div>
            {Object.entries(groupedChoices).map(([group, groupChoices]) => (
                <div key={group} className="choice-group">
                    {group !== 'all' && <h3 className="group-title">{group}</h3>}
                    {groupChoices.map(choice => (
                        <Card
                        key={choice.name}
                        id={choice.name}
                            style={{
                                width: '100%',
                                marginBottom: '1em',
                                border: newlyAddedChoice === choice.name ? '2px solid #007cba' : 'none',
                                transition: 'border 0.3s',
                            }}
                            className={newlyAddedChoice === choice.name ? 'highlight' : 'test'}
                        >
                            <CardHeader onClick={() => toggleOpen(choice.name)}>
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

                            {openIndexes.includes(choice.name) && (
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

// Add PropTypes validation
ChoiceCards.propTypes = {
    choices: PropTypes.arrayOf(PropTypes.shape({
        id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
        name: PropTypes.string.isRequired,
    })).isRequired,
    groupBy: PropTypes.string,
    newlyAddedChoice: PropTypes.string,
    openIndexes: PropTypes.array,
    toggleOpen: PropTypes.func.isRequired
};

export default ChoiceCards;
