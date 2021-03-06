<?php get_header(); ?>
<div id="body">
<?php 	if ( get_post_meta(get_the_ID(),'small',true) === "true" ) { ?>
	<div id="content" class="widecolumn botpad">
<?php	} else { ?>
	<div id="content" class="fullcolumn botpad">
<?php	} ?>

<?php 	if (have_posts()) : while (have_posts()) : the_post(); ?>
<?php 	if ( get_post_meta(get_the_ID(),'border',true) !== "false" ) { ?>
		<div class="ld-post post" id="post-<?php the_ID(); ?>">
<?php 	if ( get_post_meta(get_the_ID(),'notitle',true) != "" ) { ?>
			<div class="head header"><h2><?php the_title(); ?></h2></div>
<?php	} ?>
			<div class="entry body">
				<?php the_content('<p class="serif">Read the rest of this page &raquo;</p>'); ?>
				<br />
			</div>
		</div>
<?php	} else { ?>
		<?php the_content('<p class="serif">Read the rest of this page &raquo;</p>'); ?>
<?php	} ?>
		<?php endwhile; endif; ?>
	<?php edit_post_link('Edit this entry.', '<div>', '</div>'); ?>
	</div>
</div>
<?php get_footer(); ?>