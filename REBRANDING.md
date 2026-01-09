# UniLinks Rebranding - University of Basel

This document outlines the rebranding of YOURLS to **UniLinks** with the University of Basel color scheme.

## Color Scheme Applied

Based on the [University of Basel's official brand colors](https://www.unibas.ch/en/University/Administration-Services/The-President-s-Office/Communications-Marketing/Web-Services/Web-Desk/Web-Corporate-Design/Online-Design-Manual/colors.html):

- **Unibas Mint**: `#A5D7D2` - Used for borders, backgrounds, and accents
- **Unibas Red**: `#D20537` - Primary brand color for links, borders, and highlights
- **Unibas Anthracite**: `#2D373C` - Text color and dark elements

## Changes Made

### 1. CSS Files Updated

#### `css/style.css`
- Background colors: Changed from blue tones to mint/light backgrounds
- Primary borders: Changed from `#2a85b3` to `#D20537` (Unibas Red)
- Link colors: Changed to `#D20537` with hover state `#A5D7D2` (Unibas Mint)
- Text colors: Changed from `#595441` to `#2D373C` (Unibas Anthracite)
- Input borders: Changed to mint color `#A5D7D2`
- Button styles: Updated to use new color scheme
- Dialog boxes: Updated delete confirmation dialog colors

#### `css/infos.css`
- Tab borders and backgrounds updated to use mint color
- Text colors updated to anthracite

### 2. Branding Text Updated

#### `includes/functions-html.php`
- Logo title: Changed from "YOURLS: Your Own URL Shortener" to "UniLinks"
- Page title: Changed from "YOURLS — Your Own URL Shortener" to "UniLinks — University Link Shortener"
- Meta description: Updated to reflect UniLinks branding
- Footer: Changed "Powered by YOURLS" to "Powered by UniLinks"

#### `includes/functions-http.php`
- User agent string: Changed from "YOURLS v..." to "UniLinks v..."

#### `admin/install.php`
- Logo alt text: Updated to "UniLinks"

### 3. Color Mapping

| Old Color | New Color | Usage |
|-----------|-----------|-------|
| `#2a85b3` (Blue) | `#D20537` (Unibas Red) | Primary links, borders, highlights |
| `#88c0eb` (Light Blue) | `#A5D7D2` (Unibas Mint) | Hover states, secondary borders |
| `#e3f3ff` (Very Light Blue) | `#f0f9f8` (Light Mint) | Backgrounds, edit rows |
| `#595441` (Brown) | `#2D373C` (Anthracite) | Text color |
| `#C7E7FF` (Light Blue) | `#A5D7D2` (Mint) | Input backgrounds, highlights |

## Remaining Items

The following items may need manual updates depending on your needs:

1. **Logo Image**: The logo file `images/yourls-logo.svg` still exists. You may want to create a new UniLinks logo.
2. **Language Files**: Translation strings in language files still contain "YOURLS" references. These are in the `user/languages/` directory.
3. **Install Page Text**: The install page uses translation functions, so text changes require language file updates.
4. **README.md**: Documentation files still reference YOURLS.

## Testing

After rebranding, test the following:
- [ ] Admin interface displays with new colors
- [ ] Links and buttons use correct colors
- [ ] Hover states work properly
- [ ] Forms and inputs display correctly
- [ ] Dialog boxes use new color scheme
- [ ] Footer shows "UniLinks" branding

## Notes

- The rebranding maintains all functionality while updating the visual identity
- Color scheme follows University of Basel's official brand guidelines
- All user-facing text has been updated to "UniLinks"
- Internal function names remain unchanged for compatibility
