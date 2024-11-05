import PropTypes from 'prop-types';
import { useState, useEffect } from '@wordpress/element';
import { Button, Flex, FlexItem, ToggleControl } from '@wordpress/components';

const SaveBar = ({ children, onSave, setUnsavedChanges, unsavedChanges, saveText = "Save Settings", isAutoSaveEnabled, setIsAutoSaveEnabled, buttonText, setMessage }) => {
    const [isSuccess, setIsSuccess] = useState(false);
    const [message, setMessageState] = useState(''); // Manage message state
    const [elapsedTime, setElapsedTime] = useState(0);
    const [intervalId, setIntervalId] = useState(null);

    // Function to update the fixed position
    function updateFixedPosition() {
        const adminMenuWrap = document.querySelector('#adminmenuwrap');
        const fixedElement = document.querySelector('.ace-save-bar');

        if (adminMenuWrap && fixedElement) {
            const adminMenuWidth = adminMenuWrap.offsetWidth;
            fixedElement.style.left = `${adminMenuWidth}px`;
        }
    }

    useEffect(() => {
        // Update position on load, resize, and scroll
        window.addEventListener('resize', updateFixedPosition);
        window.addEventListener('scroll', updateFixedPosition);
        window.addEventListener('load', updateFixedPosition);

        // Add beforeunload event to prompt the user if there are unsaved changes
        const handleBeforeUnload = (event) => {
            if (unsavedChanges) {
                event.preventDefault();
                event.returnValue = ''; // Required for Chrome to show the dialog
            }
        };
        window.addEventListener('beforeunload', handleBeforeUnload);

        // Cleanup function
        return () => {
            window.removeEventListener('resize', updateFixedPosition);
            window.removeEventListener('scroll', updateFixedPosition);
            window.removeEventListener('load', updateFixedPosition);
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, [unsavedChanges]);

    useEffect(() => {
        // Start the timer when the message is shown
        if (message === 'Time since last saved') {
            const id = setInterval(() => setElapsedTime((prev) => prev + 1), 1000);
            setIntervalId(id);
        } else if (intervalId) {
            clearInterval(intervalId);
            setIntervalId(null);
        }
    }, [message]);

    const handleClick = () => {
        onSave(false, setMessageState); // Pass setMessageState to onSave
    };

    const formatElapsedTime = (seconds) => {
        if (seconds < 60) return `${seconds} seconds ago`;
        if (seconds < 3600) return `${Math.floor(seconds / 60)} minutes ago`;
        return `${Math.floor(seconds / 3600)} hours ago`;
    };

    return (
        <div className={`ace-save-bar ${unsavedChanges ? 'unsaved-changes' : ''}`}>
            <Flex justify="space-between" align="center">
                <FlexItem>{children}</FlexItem>
                <FlexItem>
                    <Flex align="center" gap="1rem" style={{ alignItems: 'center' }}>
                        {message && (
                            <span className="save-message">
                                {message === 'Time since last saved' ? formatElapsedTime(elapsedTime) : message}
                            </span>
                        )}
                        <div style={{ margin: '0px' }}>
                            <ToggleControl
                                label="Live Save"
                                className="ace-save-bar-toggle-control"
                                checked={isAutoSaveEnabled}
                                onChange={(isChecked) => {
                                    setIsAutoSaveEnabled(isChecked);
                                    setMessageState(isChecked ? 'Live Save Enabled' : 'Live Save Disabled');
                                    setTimeout(() => {
                                        setMessageState('');
                                    }, 2000); // Clear the message after 2 seconds
                                }}
                            />
                        </div>
                        <Button
                            variant="primary"
                            onClick={handleClick}
                            className={`wp-saving-button ${unsavedChanges ? 'enabled' : ''} ${isSuccess ? 'saved' : ''}`}
                            aria-label={buttonText}
                            disabled={!unsavedChanges || isAutoSaveEnabled} // Start disabled on load
                        >
                            <span className="dashicons dashicons-update"></span> {buttonText}
                        </Button>
                    </Flex>
                </FlexItem>
            </Flex>
        </div>
    );
};

SaveBar.propTypes = {
    children: PropTypes.node,
    onSave: PropTypes.func.isRequired,
    setUnsavedChanges: PropTypes.func.isRequired,
    unsavedChanges: PropTypes.bool.isRequired,
    isAutoSaveEnabled: PropTypes.bool.isRequired, // Added this line
    setIsAutoSaveEnabled: PropTypes.func.isRequired, // Added this line
    setMessage: PropTypes.func.isRequired, // Added this line
};

export default SaveBar;