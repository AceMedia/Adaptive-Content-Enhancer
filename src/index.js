import { render } from '@wordpress/element';
import AdaptiveContentEnhancer from './components/Settings';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('ace-settings-root');
    if (root) {
        render(<AdaptiveContentEnhancer />, root);
    }
});
