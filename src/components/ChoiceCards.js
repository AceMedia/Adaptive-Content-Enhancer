import { Card, CardHeader, CardBody, Button, Flex, FlexItem, Tooltip, Icon } from '@wordpress/components';
import ChoiceFields from './ChoiceFields';

const ChoiceCards = ({
    choices,
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
}) => (
    <div>
        <h2>Option Groups</h2>
        
        {choices.map((choice, index) => (
            <Card
                key={index}
                style={{
                    width: '100%',
                    marginBottom: '20px',
                    border: newlyAddedChoice === choice.name ? '2px solid #007cba' : 'none',
                    transition: 'border 0.3s',
                }}
                className={newlyAddedChoice === choice.name ? 'highlight' : ''}
            >
                <CardHeader
                    onClick={() => toggleOpen(index)}
                    style={{
                        cursor: 'pointer',
                        padding: '10px',
                        backgroundColor: '#f5f5f5',
                        borderBottom: '1px solid #ddd',
                        borderRadius: '4px',
                    }}
                >
                    <Flex justify="space-between" align="center" gap={2}>
                        {/* Left-aligned group name */}
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
                                <strong>{choice.title || `Group ${index + 1}`}</strong>
                            </Flex>
                        </FlexItem>

                        {/* Right-aligned metadata and tags */}
                        <Flex align="center" gap={3} style={{ textAlign: 'right', width: 'auto' }}>
                            {/* Metadata for created/edited times */}
                            {choice.created && (
                                <div style={{ fontSize: '0.85em', color: '#777', display: 'flex', gap: '10px' }}>
                                    <Tooltip text={`Created at ${new Date(choice.created).toLocaleString()}`}>
                                        <p><strong>Created:</strong> {formatRelativeTime(choice.created)}</p>
                                    </Tooltip>
                                    <Tooltip text={`Last edited at ${new Date(choice.edited).toLocaleString()}`}>
                                        <p><strong>Last Edited:</strong> {formatRelativeTime(choice.edited)}</p>
                                    </Tooltip>
                                </div>
                            )}

                            {/* Display tags as styled spans */}
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
                        <Button isDestructive onClick={() => openConfirmDialog(index, choice.name)}>
                            Delete Group
                        </Button>
                    </CardBody>
                )}
            </Card>
        ))}
    </div>
);

export default ChoiceCards;
