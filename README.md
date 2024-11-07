### Adaptive Content Enhancer (WP ACE)
Contributors: Shane Rounce  
Tags: block, custom fields, dynamic content, editor enhancements  
Tested up to: 6.7  
Stable tag: 0.421.0  
License: GPL-2.0-or-later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  
Short 

Adaptive Content Enhancer offers flexible custom field management for the WordPress block editor, enabling interactive content editing.

---

### Description

WP ACE offers a streamlined way to manage custom fields and dropdowns in the WordPress editor, ~including support for ACF field integration~, custom dropdown groups, and true/false fields. With an intuitive interface, users can define field groups, manage options, and render dynamic content on the front end based on selected attributes. WP ACE is ideal for developers looking to enhance content creation capabilities with robust customization features.

Key Features:
- **Create and manage custom field groups** (dropdowns, checkboxes, radio buttons, and true/false toggles).
- **Seamless integration into the WordPress block editor** - Add fields directly into the editor, with customizable behaviors and settings.
- **Enhanced settings management** - Configure options and field groups with an intuitive UI, supported by modular components.
- **Dynamic data fetching and rendering** - Utilize advanced REST API support to fetch and render content based on custom configurations.
- **Real-time AJAX processing** - Save and manage field groups and options without page reloads, ensuring a smooth user experience.
- **Extendable functionality** - Developers can extend WP ACE with custom filters and settings for more advanced scenarios.
- **Role-based access control** - Assign specific roles to field groups to control who can view or edit them.
- **Post type association** - Associate field groups with specific post types for more granular control.
- **Tag management** - Create, edit, and assign tags to field groups for better organization and filtering.
- **Bulk actions** - Perform bulk actions such as deleting multiple field groups at once.
- **Customizable UI** - Customize the appearance of field groups and options with color pickers and other UI controls.
- **Auto-save and live save** - Enable auto-save and live save features to ensure changes are saved in real-time.
- **Pagination and sorting** - Navigate through field groups with pagination and sort them by various criteria.
- **Search functionality** - Search for field groups and options using a built-in search feature.
- **Custom hooks** - Utilize custom hooks for fetching choices and saving options to the API.
- **Customizable styles** - Apply custom styles to field groups and options using SCSS.

---

### API Endpoints

WP ACE provides several custom REST API endpoints to manage field groups and options:

- **GET /ace/v1/get-choices**: Fetches the available choices.
- **POST /ace/v1/save-options**: Saves new or updated choices.
- **POST /ace/v1/delete-option**: Deletes a specified choice.
- **GET /ace/v1/roles**: Fetches the available user roles.

These endpoints facilitate dynamic data fetching and management within the WordPress admin interface.

---

### Components

WP ACE is built with modular React components to ensure a seamless user experience:

- **Settings**: The main component for managing field groups and options.
- **ChoiceCards**: Displays individual choice cards with options.
- **SideBar**: Provides filtering and sorting options for the choices.
- **SaveBar**: Handles saving changes and displaying save status.
- **MainSettingsPanel**: The primary panel for configuring settings and managing choices.

Each component is designed to be reusable and extendable, allowing for easy customization and enhancement.

---

### State Management

WP ACE uses React hooks for state management:

- **useState**: Manages local state within components.
- **useEffect**: Handles side effects, such as fetching data from the API.
- **useReducer**: (If applicable) Manages complex state logic.

State is lifted to the highest necessary level to ensure data consistency across components.

---

### Custom Hooks

WP ACE includes custom hooks to encapsulate reusable logic:

- **useFetchFields**: Fetches choices from the API and manages loading state.
- **useSaveChoice**: Handles saving a choice to the API and updating local state.

These hooks simplify component logic and promote code reuse.

---

### Build and Development

To build and develop WP ACE, follow these steps:

1. **Install Dependencies**: Run `npm install` to install the necessary dependencies.
2. **Build the Project**: Use `npm run build` to build the project for production.
3. **Start Development Server**: Use `npm run start` to start the development server with live reloading.
4. **Build JavaScript**: Use `npm run build-js` to build the JavaScript files.
5. **Watch JavaScript**: Use `npm run dev-js` to watch for changes in JavaScript files and rebuild automatically.
6. **Build CSS**: Use `npm run build-css` to build the CSS files from SCSS.
7. **Watch CSS**: Use `npm run watch-css` to watch for changes in SCSS files and rebuild CSS automatically.
8. **Clean Assets**: Use `npm run clean-assets` to clean the assets directory.
9. **Format Code**: Use `npm run format` to format the codebase.
10. **Lint CSS**: Use `npm run lint:css` to lint the CSS files.
11. **Lint JavaScript**: Use `npm run lint:js` to lint the JavaScript files.
12. **Update Packages**: Use `npm run packages-update` to update the npm packages.
13. **Create Plugin Zip**: Use `npm run plugin-zip` to create a zip file of the plugin for distribution.

The project uses Webpack for bundling and Babel for transpiling modern JavaScript.

---

### Installation

1. Upload the plugin files to the `/wp-content/plugins/wp-adaptive-content-enhancer` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to the 'Fields' ~and 'ACF Import'~ menu under 'Settings' to configure field groups ~and import ACF fields~.

---

### Frequently Asked Questions

**Q: How do I add a new dropdown group or true/false field?**
A: Navigate to the 'Fields' settings page in the admin panel. Here you can create, edit, or delete field groups, including options for dropdowns, checkboxes, and true/false toggles.

**Q: How do I lock down field groups based on user roles?**
A: When creating or editing a field group, you can assign specific roles to the group. This controls who can view or edit the group. Navigate to the 'Fields' settings page, select a field group, and use the 'Associated Roles' section to assign roles.

**Q: How do I associate field groups with specific post types?**
A: You can associate field groups with specific post types to control where they appear. Navigate to the 'Fields' settings page, select a field group, and use the 'Associated Post Types' section to assign post types.

**Q: How do I manage tags for field groups?**
A: Navigate to the 'Fields' settings page, select a field group, and use the 'Tags' section to assign tags. You can create new tags or select existing ones. Tags help in organizing and filtering field groups.

**Q: How do I use the bulk edit feature?**
A: In the 'Fields' settings page, enable 'Quick Edit' mode. You can then select multiple field groups using the checkboxes and perform bulk actions such as deleting selected groups. Use the 'Toggle All' switch to select or deselect all groups at once.

**Q: Does WP ACE support AJAX-based updates?**
A: Absolutely. WP ACE uses AJAX for real-time management of field groups and options, ensuring seamless updates without reloading the page.

**Q: How do I customize the appearance of field groups?**
A: You can customize the appearance of field groups using the color picker and other UI controls available in the 'Fields' settings page. This allows you to apply custom styles to field groups and options.

**Q: How do I enable auto-save and live save features?**
A: Navigate to the 'Settings' page and enable the auto-save and live save options. This ensures that changes are saved in real-time without the need for manual saving.

**Q: How do I search for field groups and options?**
A: Use the built-in search feature available in the 'Fields' settings page. Enter your search query in the search bar to find specific field groups and options.

**Q: How do I sort and paginate field groups?**
A: Use the sorting and pagination controls available in the 'Fields' settings page. You can sort field groups by various criteria and navigate through them using the pagination controls.

**Q: How do I use custom hooks in WP ACE?**
A: WP ACE includes custom hooks such as useFetchFields and useSaveChoice to encapsulate reusable logic. These hooks simplify component logic and promote code reuse. Refer to the documentation for detailed usage instructions.

**Q: How do I apply custom styles to field groups and options?**
A: WP ACE allows you to apply custom styles using SCSS. Customize the appearance of field groups and options by modifying the SCSS files included in the plugin.

---

### Screenshots

1. **Settings Screen** - The main interface for managing dropdown groups and true/false fields.
2. **Editor Integration** - Example of how WP ACE integrates seamlessly within the WordPress block editor.
3. ~**ACF Import** - Easily import ACF fields directly into WP ACE for seamless integration.~

---

### Changelog

**0.421.0**
- Enhanced Role-based Access Control: Improved the ability to assign specific roles to field groups, controlling who can view or edit them.
- Post Type Association: Added functionality to associate field groups with specific post types for more granular control.
- Tag Management: Enhanced tag management features, allowing users to create, edit, and assign tags to field groups for better organization and filtering.
- Bulk Actions: Improved bulk actions functionality, including the ability to delete multiple field groups at once.
- Customizable UI: Added more UI customization options, including color pickers and other controls to customize the appearance of field groups and options.
- Auto-save and Auto Save: Implemented auto-save and live save features to ensure changes are saved in real-time without manual intervention.
- Pagination and Sorting: Enhanced pagination and sorting controls to navigate through field groups and sort them by various criteria.
- Search Functionality: Added a built-in search feature to find specific field groups and options easily.
- Custom Hooks: Introduced custom hooks such as useFetchChoices and useSaveChoice to encapsulate reusable logic and simplify component development.
- Customizable Styles: Enabled the application of custom styles to field groups and options using SCSS.
- Improved Error Handling: Enhanced error handling for meta value updates to prevent issues when saving posts.
- UI Enhancements: Various UI improvements for a better user experience, including updates to the settings page and editor integration.
- Bug Fixes: Addressed several bugs and issues reported since the initial release, improving overall stability and performance.


**0.420.0**
- Initial release.
- Added support for custom dropdown, checkbox, radio button, and true/false fields.
- Integrated REST API support for dynamically fetching and rendering field data.
- **AJAX support** for real-time field group management.
- **Settings and block integration components** for an enhanced editor experience.

---

### Additional Information

WP ACE provides a comprehensive suite of tools for integrating custom field data into WordPress content. Whether you're enhancing editorial experiences or developing custom content management solutions, WP ACE makes it easy to extend the power of the WordPress block editor with adaptive content fields.
