<div class="wrap">
    <h2>True/False Fields</h2>
    <table class="wp-list-table widefat fixed striped table-view-excerpt">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-name">Field Name</th>
                <th scope="col" class="manage-column column-true-label">Label for True</th>
                <th scope="col" class="manage-column column-false-label">Label for False</th>
                <th scope="col" class="manage-column column-action">Action</th>
            </tr>
        </thead>
        <tbody id="truefalse" data-wp-lists="list:true-false-fields">
            <?php if (!empty($true_false_fields)) : ?>
                <?php foreach ($true_false_fields as $field_index => $field) : ?>
                    <?php if (empty($edit_key) || create_underscored_value($field['name']) === $edit_key) : ?>
                        <tr id="field-<?php echo esc_attr($field_index); ?>" class="level-0">
                            <td class="name column-name has-row-actions column-primary">
                                <input 
                                    type="text" 
                                    name="true_false_fields[<?php echo esc_attr($field_index); ?>][name]" 
                                    value="<?php echo esc_attr($field['name']); ?>" 
                                    placeholder="Field Name" 
                                    readonly
                                />
                            </td>
                            <td class="true-label column-true-label">
                                <input 
                                    type="text" 
                                    name="true_false_fields[<?php echo esc_attr($field_index); ?>][true_label]" 
                                    value="<?php echo esc_attr($field['true_label']); ?>" 
                                    placeholder="Label for True" 
                                />
                            </td>
                            <td class="false-label column-false-label">
                                <input 
                                    type="text" 
                                    name="true_false_fields[<?php echo esc_attr($field_index); ?>][false_label]" 
                                    value="<?php echo esc_attr($field['false_label']); ?>" 
                                    placeholder="Label for False" 
                                />
                            </td>
                            <td class="action column-action">
                                <label for="show_in_rest_<?php echo esc_attr($field_index); ?>">Show in Rest API</label>
                                <input 
                                    type="checkbox" 
                                    id="show_in_rest_<?php echo esc_attr($field_index); ?>" 
                                    name="true_false_fields[<?php echo esc_attr($field_index); ?>][show_in_rest]" 
                                    value="1" 
                                    <?php checked(!empty($field['show_in_rest'])); ?> 
                                />
                                <a href="<?php echo admin_url('admin.php?page=ace-dropdown-groups&edit=' . esc_attr(create_underscored_value($field['name']))); ?>" class="button">Edit Field</a>
                                <button type="button" class="button remove-this" data-group-name="<?php echo create_underscored_value($field['name']); ?>">Remove Group</button>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="4">
                        <p>No True/False fields found. <button type="button" class="button add-field">Add Field</button></p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-name" colspan="4">
                    <p><button type="button" class="button add-field">Add True/False Field</button></p>
                </th>
            </tr>
        </tfoot>
    </table>
</div>
