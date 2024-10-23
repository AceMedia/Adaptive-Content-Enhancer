import { render } from '@wordpress/element';
import AdaptiveContentEnhancer from './components/settings';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('ace-settings-root');
    if (root) {
        render(<AdaptiveContentEnhancer />, root);
    }
});
