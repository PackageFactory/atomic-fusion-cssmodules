# PackageFactory.AtomicFusion.CssModules

> [CSS Modules](https://github.com/css-modules/css-modules) for Atomic.Fusion

## Why?

[Atomic.Fusion](https://github.com/PackageFactory/atomic-fusion) enables you to implement a frontend component
architecture in [Neos.Fusion](https://github.com/neos/neos-development-collection/tree/master/Neos.Fusion). In similar
environments (like [React](https://reactjs.org/) or [Vue.js](https://vuejs.org/)),
[CSS Modules](https://github.com/css-modules/css-modules) have been proven to be a robust solution to avoid CSS global
namespace issues and to make your styles portable alongside your components.

To enable this technology felt like a natural extension to the ecosystem of Atomic.Fusion.

## What do CSS modules do?

In short: [CSS Modules](https://github.com/css-modules/css-modules) convert your CSS class names and other global
identifiers (like `@keyframes`) into unique identifiers. When processing your CSS files, a JSON file is created, that
maps the original class name to the new unique one.

This way it is ensured, that you can always use speaking identifiers that make sense in the context of your component,
without having to worry about potential global naming conflicts.

**PackageFactory.AtomicFusion.CssModules** helps you to include those \*.json files at the right place. It expects them
to be present alongisde your Fusion components already - it won't generate them.

In order to generate CSS Module files, you have to configure your frontend build pipeline accordingly. An example of
how this could look like is given in the next section.

## Setting up CSS modules

> The following describes an example setup for postcss and postcss-modules. This is not the only way to create such a
> setup. For further information on this, I suggest you have a look at the [postcss-cli](https://github.com/postcss/postcss-cli)
> and [postcss-modules](https://github.com/css-modules/postcss-modules) repositories.

CSS modules require Node JS and two essential dependencies. Make sure, Node JS is available to your set up and that
you have a valid `package.json` file at the root of your repository (or whereever you prefer it to reside).

Then install the following packages:

```sh
yarn add --dev postcss postcss-modules
```

This example also uses `postcss-cli`:

```sh
yarn add --dev postcss-cli
```

Then create a `postcss.config.js` in the same directory as your `package.json`:

```js
module.exports = {
    plugins: [
        require('postcss-modules')
    ]
}
```

Create a Makefile containing a recipe for building your CSS in the same directory as your `package.json`:

```Makefile
SHELL=/bin/bash
export PATH := $(PATH):./node_modules/.bin
export SITE_PACKGE_DIR="./Packages/Sites/Vendor.Site"

build-css:
	# Build CSS with postcss
	postcss \
		${SITE_PACKGE_DIR}/Resources/Private/Fusion/**/*.css \
		--dir ${SITE_PACKGE_DIR}/Resources/Public/Styles/temp
	# Concat all built CSS files
	cat ${SITE_PACKGE_DIR}/Resources/Public/Styles/temp/* > ${SITE_PACKGE_DIR}/Resources/Public/Styles/main.css
	# Remove temporary CSS files
	rm -rf ${SITE_PACKGE_DIR}/Resources/Public/Styles/temp
```

When you run `make build-css`, all \*.css files you have placed alongside your \*.fusion component files will
afterwards be accompanied by a corresponding `*.css.json` file containing the map mentioned above.

## Usage in Fusion

Consider the following css file (let's say `Vendor.Site/Resources/Private/Fusion/MyComponent/MyComponent.css`):

```CSS
.myComponent {
	display: block;
}

.isActive {
	background-color: blue;
}
```

After running `make build-css`, a file `Vendor.Site/Resources/Private/Fusion/MyComponent/MyComponent.css.json` should
appear.

In your `Vendor.Site/Resources/Private/Fusion/MyComponent/MyComponent.fusion` file, you can now access the class names
like this:

```Fusion
prototype(Vendor.Site:MyAwesomeComponent) < prototype(PackageFactory.AtomicFusion:Component) {
	# Fusion
	renderer = Neos.Fusion:Tag {
		attributes.class = ${AtomicFusion.classNames(styles.myComponent, props.isActive && styles.isActive)}
	}

	# AFX
	renderer = afx`
		<div
			class={AtomicFusion.classNames(
				styles.myComponent,
				props.isActive && styles.isActive
			)}
			>
		</div>
	`
}
```

Notice that within your components `renderer` path, you can now access a special `styles` context, that contains the
class name map.

> **Side note:** `AtomicFusion.classNames` is a helper method to concatenate class names conditionally. More on this can
> found in the [Atomic.Fusion main repository](https://github.com/PackageFactory/atomic-fusion).

## Discovery of CSS module files

Fusion actually holds no information about the file a prototype is defined in. So, to enable the discovery of files put
alongside those component \*.fusion files, we need to configure a lookup pattern:

```yaml
PackageFactory:
  AtomicFusion:
    CssModules:
      tryFiles:
        - resource://{packageKey}/Private/Fusion/{componentPath}.css.json
```

With the above configuration the CSS module file for the prototype `prototype(Vendor.Site:MyAwesomeComponent)` will be
assumed as `resource://Vendor.Site/Private/Fusion/MyAwesomeComponent.css.json`.

`{packageKey}` and `{componentPath}` are variables that will be replaced with runtime information of the respective
fusion prototype.

The following variables are considered:

* `{prototypeName}` - The entire prototype name
* `{packageKey}` - The part of the prototype name before the `:`
* `{componentName}` - The part of the prototype name after the `:`
* `{componentBaseName}` - The last part of the componentName, if seperated by dots (for `Vendor.Site:Atom.Button` that would be: `Button`)
* `{componentPath}` - Similar to `{componentName}` with all dots being replaced by Directory Separators.

By default, the package looks at the following patterns:

```
resource://{packageKey}/Private/Fusion/{componentPath}.css.json
resource://{packageKey}/Private/Fusion/{componentPath}/{componentBaseName}.css.json
resource://{packageKey}/Private/Fusion/{componentPath}/style.css.json
resource://{packageKey}/Private/Fusion/{componentPath}/Style.css.json
resource://{packageKey}/Private/Fusion/{componentPath}/styles.css.json
resource://{packageKey}/Private/Fusion/{componentPath}/Styles.css.json
resource://{packageKey}/Private/Fusion/{componentPath}/index.css.json
resource://{packageKey}/Private/Fusion/{componentPath}/Index.css.json
resource://{packageKey}/Private/Fusion/{componentPath}/component.css.json
resource://{packageKey}/Private/Fusion/{componentPath}/Component.css.json
```

## Caveats

Recently, the native component prototype `Neos.Fusion:Component` has arrived in the Neos.Fusion core. Unfortunately,
this package won't work with this new prototype and relies on `PackageFactory.AtomicFusion:Component` to be present.

This will likely change in the future, either through this package or a PR to Neos.Fusion.

If you still want to use this package with an existing code base, that relies on `Neos.Fusion:Component`, you could
replace the `Neos.Fusion:Component` standard implementation with the one for `PackageFactory.AtomicFusion:Component`:

```
prototype(Neos.Fusion:Component) {
    @class = 'PackageFactory\\AtomicFusion\\FusionObjects\\ComponentImplementation'
}
```

`PackageFactory.AtomicFusion:Component` is fully compatible to `Neos.Fusion:Component`, but you should be nonetheless
aware:

*THIS IS NOT THE JEDI WAY!*

## License

see [LICENSE.md](./LICENSE.md)
