<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Documentation generator.
 *
 * @package    Kohana/Userguide
 * @category   Base
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Kohana_Kodoc {

	public static function factory($class)
	{
		return new Kodoc_Class($class);
	}

	/**
	 * Creates an html list of all classes sorted by category (or package if no category)
	 *
	 * @return   string   the html for the menu
	 */
	public static function menu()
	{
		$classes = Kodoc::classes();

		foreach ($classes as $class)
		{
			if (isset($classes['kohana_'.$class]))
			{
				// Remove extended classes
				unset($classes['kohana_'.$class]);
			}
		}

		ksort($classes);

		$menu = array();

		$route = Route::get('docs/api');

		foreach ($classes as $class)
		{
			$class = Kodoc::factory($class);

			$link = HTML::anchor($route->uri(array('class' => $class->class->name)), $class->class->name);

			if (isset($class->tags['package']))
			{
				foreach ($class->tags['package'] as $package)
				{
					if (isset($class->tags['category']))
					{
						foreach ($class->tags['category'] as $category)
						{
							$menu[$package][$category][] = $link;
						}
					}
					else
					{
						$menu[$package]['Core'][] = $link;
					}
				}
			}
			else
			{
				$menu['Unknown']['Base'][] = $link;
			}
		}

		// Sort the packages
		ksort($menu);

		return View::factory('userguide/api/menu')
			->bind('menu', $menu);
	}

	/**
	 * Returns an array of all the classes available, built by listing all files in the classes folder and then trying to create that class.
	 * 
	 * This means any empty class files (as in complety empty) will cause an exception
	 *
	 * @param   array   array of files, obtained using Kohana::list_files
	 * @retur   array   an array of all the class names
	 */
	public static function classes(array $list = NULL)
	{
		if ($list === NULL)
		{
			$list = Kohana::list_files('classes');
		}

		$classes = array();

		foreach ($list as $name => $path)
		{
			if (is_array($path))
			{
				$classes += Kodoc::classes($path);
			}
			else
			{
				// Remove "classes/" and the extension
				$class = substr($name, 8, -(strlen(EXT)));

				// Convert slashes to underscores
				$class = str_replace(DIRECTORY_SEPARATOR, '_', strtolower($class));

				$classes[$class] = $class;
			}
		}

		return $classes;
	}

	/**
	 * Get all classes and methods.  Used on index page.
	 *
	 * >  I personally don't the current index page, but this could be useful for namespacing/packaging
	 * >  For example:  class_methods( Kohana::list_files('classes/sprig') ) could make a nice index page for the sprig package in the api browser
	 * >     ~bluehawk
	 *
	 */
	public static function class_methods(array $list = NULL)
	{
		$list = Kodoc::classes($list);

		$classes = array();

		foreach ($list as $class)
		{
			$_class = new ReflectionClass($class);

			if (stripos($_class->name, 'Kohana') === 0)
			{
				// Skip the extension stuff stuff
				continue;
			}

			$methods = array();

			foreach ($_class->getMethods() as $_method)
			{
				$methods[] = $_method->name;
			}

			sort($methods);

			$classes[$_class->name] = $methods;
		}

		return $classes;
	}

	/**
	 * Parse a comment to extract the description and the tags
	 *
	 * @param   string  the comment retreived using ReflectionClass->getDocComment()
	 * @return  array   array(string $description, array $tags)
	 */
	public static function parse($comment)
	{
		// Normalize all new lines to \n
		$comment = str_replace(array("\r\n", "\n"), "\n", $comment);

		// Remove the phpdoc open/close tags and split
		$comment = array_slice(explode("\n", $comment), 1, -1);

		// Tag content
		$tags = array();

		foreach ($comment as $i => $line)
		{
			// Remove all leading whitespace
			$line = preg_replace('/^\s*\* ?/m', '', $line);

			// Search this line for a tag
			if (preg_match('/^@(\S+)(?:\s*(.+))?$/', $line, $matches))
			{
				// This is a tag line
				unset($comment[$i]);

				$name = $matches[1];
				$text = isset($matches[2]) ? $matches[2] : '';

				switch ($name)
				{
					case 'license':
						if (strpos($text, '://') !== FALSE)
						{
							// Convert the lincense into a link
							$text = HTML::anchor($text);
						}
					break;
					case 'copyright':
						if (strpos($text, '(c)') !== FALSE)
						{
							// Convert the copyright sign
							$text = str_replace('(c)', '&copy;', $text);
						}
					break;
					case 'throws':
						$text = HTML::anchor(Route::get('docs/api')->uri(array('class' => $text)), $text);
					break;
					case 'uses':
						if (preg_match('/^([a-z_]+)::([a-z_]+)$/i', $text, $matches))
						{
							// Make a class#method API link
							$text = HTML::anchor(Route::get('docs/api')->uri(array('class' => $matches[1])).'#'.$matches[2], $text);
						}
					break;
				}

				// Add the tag
				$tags[$name][] = $text;
			}
			else
			{
				// Overwrite the comment line
				$comment[$i] = (string) $line;
			}
		}

		// Concat the comment lines back to a block of text
		if ($comment = trim(implode("\n", $comment)))
		{
			// Parse the comment with Markdown
			$comment = Markdown($comment);
		}

		return array($comment, $tags);
	}

	/**
	 * Get the source of a function
	 *
	 * @param  string   the filename
	 * @param  int      start line?
	 * @param  int      end line?
	 */
	public static function source($file, $start, $end)
	{
		if ( ! $file)
		{
			return FALSE;
		}

		$file = file($file, FILE_IGNORE_NEW_LINES);

		$file = array_slice($file, $start - 1, $end - $start + 1);

		if (preg_match('/^(\s+)/', $file[0], $matches))
		{
			$padding = strlen($matches[1]);

			foreach ($file as & $line)
			{
				$line = substr($line, $padding);
			}
		}

		return implode("\n", $file);
	}

} // End Kodoc
