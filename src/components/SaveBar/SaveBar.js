import PropTypes from 'prop-types';
import { useState, useEffect } from '@wordpress/element';
import { Button, Flex, FlexItem, ToggleControl } from '@wordpress/components';
import './SaveBar.scss'; // Import the SCSS styles

const SaveBar = ({ children, onSave, saveText = "Save Settings" }) => {
    const [buttonText, setButtonText] = useState(saveText);
    const [isSuccess, setIsSuccess] = useState(false);
    const [message, setMessage] = useState(''); // Manage message state
    const [elapsedTime, setElapsedTime] = useState(0);
    const [intervalId, setIntervalId] = useState(null);
    const [unsavedChanges, setUnsavedChanges] = useState(false); // Track unsaved changes
    const [isLiveSaveEnabled, setIsLiveSaveEnabled] = useState(false); // Track live save state

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

        // Cleanup function
        return () => {
            window.removeEventListener('resize', updateFixedPosition);
            window.removeEventListener('scroll', updateFixedPosition);
            window.removeEventListener('load', updateFixedPosition);
        };
    }, []);

    // Mutation observer to track DOM changes
    useEffect(() => {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach(mutation => {
                // Check if the mutation target is outside of the SaveBar
                if (!document.querySelector('.ace-save-bar').contains(mutation.target)) {
                    setUnsavedChanges(true); // Mark unsaved changes
                }
            });
        });
    
        // Start observing changes to the body or a specific container
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
        });
    
        // Cleanup observer on unmount
        return () => {
            observer.disconnect();
        };
    }, []);

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
        if (typeof onSave === 'function') {
            setButtonText('Saving...'); // Change button text
            setMessage(''); // Reset message visibility for the saving process
    
            onSave().then(response => {
                if (response.success) {
                    setButtonText('Saved!'); // Change button text on success
                    setIsSuccess(true);
                    setUnsavedChanges(false); // Reset unsaved changes state
                    setElapsedTime(0); // Reset elapsed time
                    setMessage('Time since last saved'); // Show time since last saved
    
                    // Start the timer for elapsed time
                    const id = setInterval(() => {
                        setElapsedTime((prev) => prev + 1);
                    }, 1000);
                    setIntervalId(id);
                    
                    // Clear the message after success but keep it visible
                    setTimeout(() => {
                        setButtonText(saveText); // Reset to original text
                    }, 2500);
                } else {
                    // Handle failure if needed
                    setButtonText('Failed!'); // Change button text on failure
                }
            }).catch(() => {
                setButtonText('Failed!'); // Handle error
            });
        }
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
                                checked={isLiveSaveEnabled}
                                onChange={(isChecked) => {
                                    setIsLiveSaveEnabled(isChecked);
                                    setMessage(isChecked ? 'Live Save Enabled' : 'Live Save Disabled');
                                }}
                            />
                        </div>
                        <Button 
                            variant="primary"
                            onClick={handleClick} 
                            className={`wp-saving-button ${isSuccess ? 'saved' : ''}`}
                            aria-label={buttonText}
                            disabled={isLiveSaveEnabled} // Disable button if live save is enabled
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
    saveText: PropTypes.string,
};

export default SaveBar;
