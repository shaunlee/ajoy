<?php $this->beginExtends('layouts'); ?>

<h1><?php echo $this->encode($title); ?></h1>

<p><strong>Author:</strong> <?php echo $this->encode($author); ?></p>

<p>
    Ajoy framework, another PHP web framework just for fun.
</p>

<p>Page refreshed at: <?php echo $refreshed_at; ?></p>

<?php $this->endExtends(); ?>
