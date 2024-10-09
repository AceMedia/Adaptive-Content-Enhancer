<div class="wrap">
    <h2>Options</h2>
    <table class="wp-list-table widefat fixed striped table-view-excerpt" id="dropdown-groups-table">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-name">Group Name</th>
                <th scope="col" class="manage-column column-options">Options</th>
                <th scope="col" class="manage-column column-action">Action</th>
            </tr>
        </thead>
        <tbody id="the-list" data-wp-lists="list:dropdown-groups">
            
        <?php if (!empty($dropdown_groups)) : ?>
            <?php foreach ($dropdown_groups as $group_index => $group) : ?>
                <?php $meta_key = create_underscored_value($group['name']); ?>
                
                <?php if (empty($edit_key) || create_underscored_value($group['name']) === $edit_key) : ?>
                    <tr class="dropdown-group-row level-0">
                        <td class="name column-name has-row-actions column-primary" style="vertical-align: top;">
                            <input type="text" name="dropdown_groups[<?php echo esc_attr($group_index); ?>][name]" value="<?php echo esc_attr($group['name']); ?>" <?php echo !empty($group['name']) ? 'readonly' : ''; ?> />
                            <p><strong>Meta Key:</strong> <?php echo esc_html($meta_key); ?></p>
                        </td>
                        <td class="options column-options" style="vertical-align: top;">
                            <table class="group-options-table">
                                <thead>
                                    <tr>
                                        <th>Option Label</th>
                                        <th>Option Value</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($group['options'])) : ?>
                                        <?php foreach ($group['options'] as $option_index => $option) : ?>
                                            <tr>
                                                <td style="vertical-align: top;">
                                                    <input type="text" name="dropdown_groups[<?php echo esc_attr($group_index); ?>][options][<?php echo esc_attr($option_index); ?>][label]" value="<?php echo esc_attr($option['label']); ?>" />
                                                </td>
                                                <td>
                                                    <input type="text" name="dropdown_groups[<?php echo esc_attr($group_index); ?>][options][<?php echo esc_attr($option_index); ?>][value]" value="<?php echo esc_attr($option['value']); ?>" <?php echo !empty($option['value']) ? 'readonly' : ''; ?> />
                                                </td>
                                                <td>
                                                    <button type="button" class="button remove-option">Remove</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <button type="button" class="button add-option">Add Option</button>
                        </td>
                        <td class="action column-action">
                            <label for="show_in_rest_<?php echo esc_attr($group_index); ?>">Show in Rest API</label>
                            <input 
                                type="checkbox" 
                                id="show_in_rest_<?php echo esc_attr($group_index); ?>" 
                                name="dropdown_groups[<?php echo esc_attr($group_index); ?>][show_in_rest]" 
                                value="1" 
                                <?php checked(!empty($group['show_in_rest'])); ?> 
                            />
                            <a href="<?php echo admin_url('admin.php?page=ace-dropdown-groups&edit=' . esc_attr($meta_key)); ?>" class="button">Edit Group</a>
                            <button type="button" class="button remove-this" data-group-name="<?php echo esc_attr($meta_key); ?>">Remove Group</button>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-name" colspan="3">
                    <p><button type="button" class="button add-group">Add Dropdown Group</button></p>
                </th>
            </tr>
        </tfoot>
    </table>
</div>
