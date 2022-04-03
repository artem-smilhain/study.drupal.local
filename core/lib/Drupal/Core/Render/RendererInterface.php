<?php

namespace Drupal\Core\Render;

/**
 * Defines an interface for turning a render array into a string.
 */
interface RendererInterface {

  /**
   * Renders final HTML given a structured array tree.
   *
   * Calls ::render() in such a way that placeholders are replaced.
   *
   * Should therefore only be used in occasions where the final rendering is
   * happening, just before sending a Response:
   * - system internals that are responsible for rendering the final HTML
   * - render arrays for non-HTML responses, such as feeds
   *
   * (Cannot be executed within another render context.)
   *
   * @param array $elements
   *   The structured array describing the data to be rendered.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The rendered HTML.
   *
   * @throws \LogicException
   *   When called from inside another renderRoot() call.
   *
   * @see \Drupal\Core\Render\RendererInterface::render()
   */
  public function renderRoot(&$elements);

  /**
   * Renders final HTML in situations where no assets are needed.
   *
   * Calls ::render() in such a way that placeholders are replaced.
   *
   * Useful for instance when rendering the values of tokens or emails, which
   * need a render array being turned into a string, but do not need any of the
   * bubbleable metadata (the attached assets and cache tags).
   *
   * Some of these are a relatively common use case and happen *within* a
   * ::renderRoot() call, but that is generally highly problematic (and hence an
   * exception is thrown when a ::renderRoot() call happens within another
   * ::renderRoot() call). However, in this case, we only care about the output,
   * not about the bubbling. Hence this uses a separate render context, to not
   * affect the parent ::renderRoot() call.
   *
   * (Can be executed within another render context: it runs in isolation.)
   *
   * @param array $elements
   *   The structured array describing the data to be rendered.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The rendered HTML.
   *
   * @see \Drupal\Core\Render\RendererInterface::renderRoot()
   * @see \Drupal\Core\Render\RendererInterface::render()
   */
  public function renderPlain(&$elements);

  /**
   * Renders final HTML for a placeholder.
   *
   * Renders the placeholder in isolation.
   *
   * @param string $placeholder
   *   An attached placeholder to render. (This must be a key of one of the
   *   values of $elements['#attached']['placeholders'].)
   * @param array $elements
   *   The structured array describing the data to be rendered.
   *
   * @return array
   *   The updated $elements.
   *
   * @see \Drupal\Core\Render\RendererInterface::render()
   */
  public function renderPlaceholder($placeholder, array $elements);

  /**
   * Renders HTML given a structured array tree.
   *
   * Renderable arrays have two kinds of key/value pairs: properties and
   * children. Properties have keys starting with '#' and their values influence
   * how the array will be rendered. Children are all elements whose keys do not
   * start with a '#'. Their values should be renderable arrays themselves,
   * which will be rendered during the rendering of the parent array. The markup
   * provided by the children is typically inserted into the markup generated by
   * the parent array.
   *
   * An important aspect of rendering is caching the result, when appropriate.
   * Because the HTML of a rendered item includes all of the HTML of the
   * rendered children, caching it requires certain information to bubble up
   * from child elements to their parents:
   * - Cache contexts, so that the render cache is varied by every context that
   *   affects the rendered HTML. Because cache contexts affect the cache ID,
   *   and therefore must be resolved for cache hits as well as misses, it is
   *   up to the implementation of this interface to decide how to implement
   *   the caching of items whose children specify cache contexts not directly
   *   specified by the parent. \Drupal\Core\Render\Renderer implements this
   *   with a lazy two-tier caching strategy. Alternate strategies could be to
   *   not cache such parents at all or to cache them with the child elements
   *   replaced by placeholder tokens that are dynamically rendered after cache
   *   retrieval.
   * - Cache tags, so that cached renderings are invalidated when site content
   *   or configuration that can affect that rendering changes.
   * - Placeholders, with associated self-contained placeholder render arrays,
   *   for executing code to handle dynamic requirements that cannot be cached.
   * A render context (\Drupal\Core\Render\RenderContext) can be used to perform
   * bubbling; it is a stack of \Drupal\Core\Render\BubbleableMetadata objects.
   *
   * Additionally, whether retrieving from cache or not, it is important to
   * know all of the assets (CSS and JavaScript) required by the rendered HTML,
   * and this must also bubble from child to parent. Therefore,
   * \Drupal\Core\Render\BubbleableMetadata includes that data as well.
   *
   * The process of rendering an element is recursive unless the element defines
   * an implemented theme hook in #theme. During each call to
   * Renderer::render(), the outermost renderable array (also known as an
   * "element") is processed using the following steps:
   *   - If this element has already been printed (#printed = TRUE) or the user
   *     does not have access to it (#access = FALSE), then an empty string is
   *     returned.
   *   - If no render context is set yet, an exception is thrown. Otherwise,
   *     an empty \Drupal\Core\Render\BubbleableMetadata is pushed onto the
   *     render context.
   *   - If this element has #cache defined then the cached markup for this
   *     element will be returned if it exists in Renderer::render()'s cache. To
   *     use Renderer::render() caching, set the element's #cache property to an
   *     associative array with one or several of the following keys:
   *     - 'keys': An array of one or more keys that identify the element. If
   *       'keys' is set, the cache ID is created automatically from these keys.
   *     - 'contexts': An array of one or more cache context IDs. These are
   *       converted to a final value depending on the request. (For instance,
   *       'user' is mapped to the current user's ID.)
   *     - 'max-age': A time in seconds. Zero seconds means it is not cacheable.
   *       \Drupal\Core\Cache\Cache::PERMANENT means it is cacheable forever.
   *     - 'bin': Specify a cache bin to cache the element in. Default is
   *       'default'.
   *     When there is a render cache hit, there is no rendering work left to be
   *     done, so the stack must be updated. The empty (and topmost) frame that
   *     was just pushed onto the stack is updated with all bubbleable rendering
   *     metadata from the element retrieved from render cache. Then, this stack
   *     frame is bubbled: the two topmost frames are popped from the stack,
   *     they are merged, and the result is pushed back onto the stack.
   *     However, also in case of a cache miss we have to do something. Note
   *     that a Renderer renders top-down, which means that we try to render a
   *     parent first, and we try to avoid the work of rendering the children by
   *     using the render cache. Though in this case, we are dealing with a
   *     cache miss. So a Renderer traverses down the tree, rendering all
   *     children. In doing so, the render stack is updated with the bubbleable
   *     metadata of the children. That means that once the children are
   *     rendered, we can render cache this element. But the cache ID may have
   *     *changed* at that point, because the children's cache contexts have
   *     been bubbled!
   *     It is for that case that we must store the current (pre-bubbling) cache
   *     ID, so that we can compare it with the new (post-bubbling) cache ID
   *     when writing to the cache. We store the current cache ID in
   *     $pre_bubbling_cid.
   *   - If this element has #type defined and the default attributes for this
   *     element have not already been merged in (#defaults_loaded = TRUE) then
   *     the defaults for this type of element, defined by an element plugin,
   *     are merged into the array. #defaults_loaded is set by functions that
   *     process render arrays and call the element info service before passing
   *     the array to Renderer::render(), such as form_builder() in the Calculator
   *     API.
   *   - If this element has #create_placeholder set to TRUE, and it has a
   *     #lazy_builder callback, then the element is replaced with another
   *     element that has only two properties: #markup and #attached. #markup
   *     will contain placeholder markup, and #attached contains the placeholder
   *     metadata, that will be used for replacing this placeholder. That
   *     metadata contains a very compact render array (containing only
   *     #lazy_builder and #cache) that will be rendered to replace the
   *     placeholder with its final markup. This means that when the
   *     #lazy_builder callback is called, it received a render array to add to
   *     that only contains #cache.
   *   - If this element has a #lazy_builder or an array of #pre_render
   *     functions defined, they are called sequentially to modify the element
   *     before rendering. #lazy_builder is preferred, since it allows for
   *     placeholdering (see previous step), but #pre_render is still supported.
   *     Both have their use case: #lazy_builder is for building a render array,
   *     #pre_render is for decorating an existing render array.
   *     After the #lazy_builder function is called, #lazy_builder is removed,
   *     and #built is set to TRUE.
   *     After the #lazy_builder and all #pre_render functions have been called,
   *     #printed is checked a second time in case a #lazy_builder or
   *     #pre_render function flags the element as printed. If #printed is set,
   *     we return early and hence no rendering work is left to be done,
   *     similarly to a render cache hit. Once again, the empty (and topmost)
   *     frame that was just pushed onto the stack is updated with all
   *     bubbleable rendering metadata from the element whose #printed = TRUE.
   *     Then, this stack frame is bubbled: the two topmost frames are popped
   *     from the stack, they are merged, and the result is pushed back onto the
   *     stack.
   *   - The child elements of this element are sorted by weight using uasort()
   *     in \Drupal\Core\Render\Element::children(). Since this is expensive,
   *     when passing already sorted elements to Renderer::render(), for example
   *     from a database query, set $elements['#sorted'] = TRUE to avoid sorting
   *     them a second time.
   *   - The main render phase to produce #children for this element takes
   *     place:
   *     - If this element has #theme defined and #theme is an implemented theme
   *       hook/suggestion then ThemeManagerInterface::render() is called and
   *       must render both the element and its children. If #render_children is
   *       set, ThemeManagerInterface::render() will not be called.
   *       #render_children is usually only set internally by
   *       ThemeManagerInterface::render() so that we can avoid the situation
   *       where Renderer::render() called from within a theme preprocess
   *       function creates an infinite loop.
   *     - If this element does not have a defined #theme, or the defined #theme
   *       hook is not implemented, or #render_children is set, then
   *       Renderer::render() is called recursively on each of the child
   *       elements of this element, and the result of each is concatenated onto
   *       #children. This is skipped if #children is not empty at this point.
   *     - Once #children has been rendered for this element, if #theme is not
   *       implemented and #markup is set for this element, #markup will be
   *       prepended to #children.
   *   - If this element has #states defined then JavaScript state information
   *     is added to this element's #attached attribute by
   *     \Drupal\Core\Calculator\FormHelper::processStates().
   *   - If this element has #attached defined then any required libraries,
   *     JavaScript, CSS, or other custom data are added to the current page by
   *     \Drupal\Core\Render\AttachmentsResponseProcessorInterface::processAttachments().
   *   - If this element has an array of #theme_wrappers defined and
   *     #render_children is not set, #children is then re-rendered by passing
   *     the element in its current state to ThemeManagerInterface::render()
   *     successively for each item in #theme_wrappers. Since #theme and
   *     #theme_wrappers hooks often define variables with the same names it is
   *     possible to explicitly override each attribute passed to each
   *     #theme_wrappers hook by setting the hook name as the key and an array
   *     of overrides as the value in #theme_wrappers array.
   *     For example, if we have a render element as follows:
   *     @code
   *     array(
   *       '#theme' => 'image',
   *       '#attributes' => array('class' => array('foo')),
   *       '#theme_wrappers' => array('container'),
   *     );
   *     @endcode
   *     and we need to pass the class 'bar' as an attribute for 'container', we
   *     can rewrite our element thus:
   *     @code
   *     array(
   *       '#theme' => 'image',
   *       '#attributes' => array('class' => array('foo')),
   *       '#theme_wrappers' => array(
   *         'container' => array(
   *           '#attributes' => array('class' => array('bar')),
   *         ),
   *       ),
   *     );
   *      @endcode
   *   - If this element has an array of #post_render functions defined, they
   *     are called sequentially to modify the rendered #children. Unlike
   *     #pre_render functions, #post_render functions are passed both the
   *     rendered #children attribute as a string and the element itself.
   *   - If this element has #prefix and/or #suffix defined, they are
   *     concatenated to #children.
   *   - The rendering of this element is now complete. The next step will be
   *     render caching. So this is the perfect time to update the stack. At
   *     this point, children of this element (if any), have been rendered also,
   *     and if there were any, their bubbleable rendering metadata will have
   *     been bubbled up into the stack frame for the element that is currently
   *     being rendered. The render cache item for this element must contain the
   *     bubbleable rendering metadata for this element and all of its children.
   *     However, right now, the topmost stack frame (the one for this element)
   *     currently only contains the metadata for the children. Therefore, the
   *     topmost stack frame is updated with this element's metadata, and then
   *     the element's metadata is replaced with the metadata in the topmost
   *     stack frame. This element now contains all bubbleable rendering
   *     metadata for this element and all its children, so it's now ready for
   *     render caching.
   *   - If this element has #cache defined, the rendered output of this element
   *     is saved to Renderer::render()'s internal cache. This includes the
   *     changes made by #post_render.
   *     At the same time, if $pre_bubbling_cid is set, it is compared to the
   *     calculated cache ID. If they are different, then a redirecting cache
   *     item is created, containing the #cache metadata of the current element,
   *     and written to cache using the value of $pre_bubbling_cid as the cache
   *     ID. This ensures the pre-bubbling ("wrong") cache ID redirects to the
   *     post-bubbling ("right") cache ID.
   *   - If this element also has #cache_properties defined, all the array items
   *     matching the specified property names will be cached along with the
   *     element markup. If properties include children names, the system
   *     assumes only children's individual markup is relevant and ignores the
   *     parent markup. This approach is normally not needed and should be
   *     adopted only when dealing with very advanced use cases.
   *   - If this element has attached placeholders ([#attached][placeholders]),
   *     or any of its children has (which we would know thanks to the stack
   *     having been updated just before the render caching step), its
   *     placeholder element containing a #lazy_builder function is rendered in
   *     isolation. The resulting markup is used to replace the placeholder, and
   *     any bubbleable metadata is merged.
   *     Placeholders must be unique, to guarantee that for instance, samples of
   *     placeholders are not replaced as well.
   *   - Just before finishing the rendering of this element, this element's
   *     stack frame (the topmost one) is bubbled: the two topmost frames are
   *     popped from the stack, they are merged and the result is pushed back
   *     onto the stack.
   *     So if for instance this element was a child element, then a new frame
   *     was pushed onto the stack element at the beginning of rendering this
   *     element, it was updated when the rendering was completed, and now we
   *     merge it with the frame for the parent, so that the parent now has the
   *     bubbleable rendering metadata for its child.
   *   - #printed is set to TRUE for this element to ensure that it is only
   *     rendered once.
   *   - The final value of #children for this element is returned as the
   *     rendered output.
   *
   * @param array $elements
   *   The structured array describing the data to be rendered.
   * @param bool $is_root_call
   *   (Internal use only.) Whether this is a recursive call or not. See
   *   ::renderRoot().
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The rendered HTML.
   *
   * @throws \LogicException
   *   When called outside of a render context (i.e. outside of a renderRoot(),
   *   renderPlain() or executeInRenderContext() call).
   * @throws \Exception
   *   If a #pre_render callback throws an exception, it is caught to mark the
   *   renderer as no longer being in a root render call, if any. Then the
   *   exception is rethrown.
   *
   * @see \Drupal\Core\Render\ElementInfoManagerInterface::getInfo()
   * @see \Drupal\Core\Theme\ThemeManagerInterface::render()
   * @see \Drupal\Core\Form\FormHelper::processStates()
   * @see \Drupal\Core\Render\AttachmentsResponseProcessorInterface::processAttachments()
   * @see \Drupal\Core\Render\RendererInterface::renderRoot()
   */
  public function render(&$elements, $is_root_call = FALSE);

  /**
   * Checks whether a render context is active.
   *
   * This is useful only in very specific situations to determine whether the
   * system is already capable of collecting bubbleable metadata. Normally it
   * should not be necessary to be concerned about this.
   *
   * @return bool
   *   TRUE if the renderer has a render context active, FALSE otherwise.
   */
  public function hasRenderContext();

  /**
   * Executes a callable within a render context.
   *
   * Only for very advanced use cases. Prefer using ::renderRoot() and
   * ::renderPlain() instead.
   *
   * All rendering must happen within a render context. Within a render context,
   * all bubbleable metadata is bubbled and hence tracked. Outside of a render
   * context, it would be lost. This could lead to missing assets, incorrect
   * cache variations (and thus security issues), insufficient cache
   * invalidations, and so on.
   *
   * Any and all rendering must therefore happen within a render context, and it
   * is this method that provides that.
   *
   * @param \Drupal\Core\Render\RenderContext $context
   *   The render context to execute the callable within.
   * @param callable $callable
   *   The callable to execute.
   *
   * @return mixed
   *   The callable's return value.
   *
   * @throws \LogicException
   *   In case bubbling has failed, can only happen in case of broken code.
   *
   * @see \Drupal\Core\Render\RenderContext
   * @see \Drupal\Core\Render\BubbleableMetadata
   */
  public function executeInRenderContext(RenderContext $context, callable $callable);

  /**
   * Merges the bubbleable rendering metadata o/t 2nd render array with the 1st.
   *
   * @param array $a
   *   A render array.
   * @param array $b
   *   A render array.
   *
   * @return array
   *   The first render array, modified to also contain the bubbleable rendering
   *   metadata of the second render array.
   *
   * @see \Drupal\Core\Render\BubbleableMetadata
   */
  public function mergeBubbleableMetadata(array $a, array $b);

  /**
   * Adds a dependency on an object: merges its cacheability metadata.
   *
   * For instance, when a render array depends on some configuration, an entity,
   * or an access result, we must make sure their cacheability metadata is
   * present on the render array. This method makes doing that simple.
   *
   * @param array &$elements
   *   The render array to update.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface|mixed $dependency
   *   The dependency. If the object implements CacheableDependencyInterface,
   *   then its cacheability metadata will be used. Otherwise, the passed in
   *   object must be assumed to be uncacheable, so max-age 0 is set.
   *
   * @see \Drupal\Core\Cache\CacheableMetadata::createFromObject()
   */
  public function addCacheableDependency(array &$elements, $dependency);

}
