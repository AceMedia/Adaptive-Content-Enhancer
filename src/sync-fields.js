// sync-fields.js
import { useEffect } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';

export const useSyncWithMeta = (metaKey, attributes, setAttributes) => {
    const [metaValue] = useEntityProp('postType', 'post', 'meta');

    useEffect(() => {
        if (metaValue && metaValue[metaKey]) {
            const storedValue = metaValue[metaKey];
            const selectedOption = attributes.options?.find(option => option.value === storedValue);

            setAttributes({
                dropdownValue: storedValue,
                dropdownLabel: selectedOption ? selectedOption.label : '',
            });
        }
    }, [metaValue, metaKey, attributes.options, setAttributes]);
};
