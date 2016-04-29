<link rel="stylesheet" href='/static/apps/noviusos_controller_front_enhancer/css/admin/route.css'>
<div class="route-container">
    <?php

    foreach ($routeFields as $route => $fields) {

        $fieldList = array_keys($fields['fields']);
        if (empty($fieldList)) {
            continue;
        }
        ?>
        <h3><?= $route ?></h3>
        <div class="route">
            <?php

            foreach ($fieldList as $fieldname) {
                ?>
                <span class="separator">/</span>
                <span class="field"><?= $fieldset->field($fieldname) ?></span>
                <?php
            }
            ?>
        </div>
        <?php
    }
    ?>
</div>
