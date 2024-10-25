import { select, subscribe, dispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

// Track updated blocks to prevent recursive updates
const updatedBlocks = new Set();

// Function to dynamically retrieve values based on block bindings
const getValuesFromBindings = (blockClientId, bindings) => {
    const values = {};
    const { getEditedPostAttribute } = select('core/editor');

    for (const [attributeName, source] of Object.entries(bindings)) {
        if (source.args?.key) {
            // Retrieve specific post data based on the meta key
            const postData = getEditedPostAttribute(source.args.key);
            values[attributeName] = postData;
        }
    }

    return values;
};

// Function to dynamically update block content
const updateBlockContent = (blockClientId, bindings) => {
    const { getBlockAttributes } = select('core/block-editor');
    const { updateBlockAttributes, selectBlock } = dispatch('core/block-editor');

    // Get the current block's attributes
    const currentAttributes = getBlockAttributes(blockClientId);
    if (!currentAttributes) return;

    // Fetch dynamic values using bindings
    const values = getValuesFromBindings(blockClientId, bindings);

    // Merge the new values with current content or set placeholder if no data
    const newContent = values.content || 'Add post data';

    console.log(`Current content: ${currentAttributes.content}`);

    // Update the block content
    updateBlockAttributes(blockClientId, {
        content: newContent,
    });

    // Force re-selection to trigger re-render
    selectBlock(blockClientId);
};

// Function to detect and handle block bindings
const detectBlockBindings = () => {
    // Store the initial state of blocks
    let previousBlocks = select(blockEditorStore).getBlocks();

    // Subscribe to changes in the block editor
    subscribe(() => {
        const currentBlocks = select(blockEditorStore).getBlocks();

        // Loop through all the blocks and check their attributes
        currentBlocks.forEach((block, index) => {
            const previousBlock = previousBlocks[index];

            // Detect changes in block attributes or new blocks
            if (!previousBlock || JSON.stringify(previousBlock.attributes) !== JSON.stringify(block.attributes)) {
                console.log(`Block ${block.name} has been connected to a binding or updated:`);

                // Check for metadata bindings and retrieve the key
                const metaKey = block.attributes?.metadata?.bindings?.content?.args?.key;

                if (metaKey) {
                    console.log(`Detected meta key: ${metaKey}`);

                    const blockClientId = block.clientId;
                    console.log(`Block client ID: ${blockClientId}`);

                    // Avoid recursive updates
                    if (block && !updatedBlocks.has(blockClientId)) {
                        // Mark this block as updated to prevent re-triggering the subscribe callback
                        updatedBlocks.add(blockClientId);

                        // Dynamically update block content using the bindings
                        const bindings = block.attributes?.metadata?.bindings || {};
                        updateBlockContent(blockClientId, bindings);

                        // Use a brief delay to avoid unnecessary processing and remove from updated set
                        setTimeout(() => updatedBlocks.delete(blockClientId), 200);
                    }
                }
            }
        });

        // Update the stored state
        previousBlocks = currentBlocks;
    });
};

// Run the detection logic
detectBlockBindings();
