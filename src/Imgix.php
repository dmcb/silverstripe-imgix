<?php

namespace PlatoCreative\Imgix;

use SilverStripe\Control\Director;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Convert;
use Imgix\UrlBuilder;

/**
 * Represents an Image via Imgix
 *
 * @package silverstripe
 * @subpackage filesystem
 */


class Imgix extends Image {
    const ORIENTATION_SQUARE = 0;
    const ORIENTATION_PORTRAIT = 1;
    const ORIENTATION_LANDSCAPE = 2;

    private static $sub_domain = null;

    private static $secure_url_token = null;

    private static $folder_path = 'assets/';

    protected $responsive = false;

    protected $parameters = array();

    private static $use_imgix = true;

    private static $casting = array(
        'Tag' => 'HTMLText',
    );

    /**
     * Return an XHTML img tag for this Image,
     * or NULL if the image file doesn't exist on the filesystem.
     *
     * @return string
     */
    public function getTag()
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::getTag();
        }

        if($this->exists()) {
            $url = $this->getURL();
            $title = ($this->Title) ? $this->Title : $this->Filename;
            if($this->Title) {
                $title = Convert::raw2att($this->Title);
            } else {
                if(preg_match("/([^\/]*)\.[a-zA-Z0-9]{1,6}$/", $title, $matches)) {
                    $title = Convert::raw2att($matches[1]);
                }
            }
            if ($this->responsive) {
                $this->responsive = false; // reset for next image
                return "<img ix-src=\"$url\" alt=\"$title\" />";
            }
            return "<img src=\"$url\" alt=\"$title\" />";
        }
    }

    /**
     * Return an XHTML img tag for this Image.
     *
     * @return string
     */
    public function forTemplate()
    {
        return $this->getTag();
    }

    /**
     * Gets the relative URL accessible through the web.
     *
     * @uses Director::baseURL()
     * @return string
     */
    public function getURL($grant = true)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::getURL();
        }

        $subDomain = $this->config()->get('sub_domain');
        if (!$subDomain) {
            user_error("Undefined sub_domain: Please Imgix sub_domain in your config", E_USER_ERROR);
        }
        $domain = "{$subDomain}.imgix.net";
        $urlBuilder = new UrlBuilder($domain);
        $urlBuilder->setUseHttps(Director::is_https());
        $urlBuilder->setSignKey($this->config()->get('secure_url_token'));
        $originalFilePath = Director::makeRelative(Parent::getURL());
        $imgixFilePath = str_ireplace($this->config()->get('folder_path'), '', $originalFilePath);

        $parameters = $this->parameters;
        $this->extend('updateParameters', $parameters);
        $url = $urlBuilder->createURL($imgixFilePath, $parameters);

        return $url;
    }

    /**
     * Alias for getURL().
     *
     * @return string
     */
    public function Link()
    {
        return $this->getURL();
    }

    /**
     * Resize this image for the CMS. Use in templates with $CMSThumbnail
     *
     * @return Image|null
     */
    public function CMSThumbnail()
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::CMSThumbnail();
        }

        return $this->Pad($this->stat('cms_thumbnail_width'),$this->stat('cms_thumbnail_height'));
    }

    /**
     * Resize this image for use as a thumbnail in a strip. Use in templates with $StripThumbnail.
     *
     * @return Image|null
     */
    public function StripThumbnail()
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::CMSThumbnail();
        }

        return $this->Fill($this->stat('strip_thumbnail_width'),$this->stat('strip_thumbnail_height'));
    }

    /**
     * Scale image proportionally to fit within the specified bounds
     *
     * @param integer $width The width to size within
     * @param integer $height The height to size within
     * @return Image|null
     */
    public function Fit($width, $height)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::Fit($width, $height);
        }

        $this->setDimensions($width, $height);
        $this->setParameter('fit','clip');
        return $this;
    }

    /**
     * Proportionally scale down this image if it is wider or taller than the specified dimensions.
     * Similar to Fit but without up-sampling. Use in templates with $FitMax.
     *
     * @uses Imgix::Fit()
     * @param integer $width The maximum width of the output image
     * @param integer $height The maximum height of the output image
     * @return Image
     */
    public function FitMax($width, $height)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::FitMax($width, $height);
        }
        $this->setDimensions($width, $height);
        $this->setParameter('fit','max');
        return $this;
    }

    /**
     * Resize and crop image to fill specified dimensions.
     * Use in templates with $Fill
     *
     * @param integer $width Width to crop to
     * @param integer $height Height to crop to
     * @return Image|null
     */
    public function Fill($width, $height)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::Fill($width, $height);
        }

        $this->setDimensions($width, $height);
        $this->setParameter('fit','crop');
        return $this;
    }

    /**
     * Crop this image to the aspect ratio defined by the specified width and height,
     * then scale down the image to those dimensions if it exceeds them.
     * Similar to Fill but without up-sampling. Use in templates with $FillMax.
     *
     * @uses Imgix::Fill()
     * @param integer $width The relative (used to determine aspect ratio) and maximum width of the output image
     * @param integer $height The relative (used to determine aspect ratio) and maximum height of the output image
     * @return Image
     */
    public function FillMax($width, $height)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::FillMax($width, $height);
        }
        $this->Fill($width, $height);
        $this->setParameter('max-w', $this->getWidth());
        $this->setParameter('max-h', $this->getHeight());
        return $this;
    }


    /**
     * Fit image to specified dimensions and fill leftover space with a solid colour (default white). Use in templates with $Pad.
     *
     * @param integer $width The width to size to
     * @param integer $height The height to size to
     * @return Image|null
     */
    public function Pad($width, $height, $backgroundColor='FFFFFF', $transparencyPercent = 0)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::Pad($width, $height, $backgroundColor='FFFFFF', $transparencyPercent = 0);
        }

        $this->setDimensions($width, $height);
        $this->setParameter('fit','fill');
        $this->setParameter('bg', $backgroundColor);
        return $this;
    }


    /**
     * Scale image proportionally by width. Use in templates with $ScaleWidth.
     *
     * @param integer $width The width to set
     * @return Image|null
     */
    public function ScaleWidth($width)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::ScaleWidth($width);
        }
        $this->setDimensions($width);
        $this->setParameter('fit','clip');
        return $this;
    }

    /**
    * Proportionally scale down this image if it is wider than the specified width.
    * Similar to ScaleWidth but without up-sampling. Use in templates with $ScaleMaxWidth.
    *
    * @uses Imgix::ScaleWidth()
    * @param integer $width The maximum width of the output image
    * @return Image
    */
    public function ScaleMaxWidth($width)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::ScaleMaxWidth($width);
        }
        $this->ScaleWidth($width);
        $this->setParameter('max-w', $this->getWidth());
        return $this;
    }

    /**
     * Scale image proportionally by height. Use in templates with $ScaleHeight.
     *
     * @param integer $height The height to set
     * @return Image|null
     */
    public function ScaleHeight($height)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::ScaleHeight($height);
        }

        $this->setDimensions(null, $height);
        $this->setParameter('fit','clip');
        return $this;
	}

    /**
     * Proportionally scale down this image if it is taller than the specified height.
     * Similar to ScaleHeight but without up-sampling. Use in templates with $ScaleMaxHeight.
     *
     * @uses Imgix::ScaleHeight()
     * @param integer $height The maximum height of the output image
     * @return Image
     */
    public function ScaleMaxHeight($height)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::ScaleMaxHeight($height);
        }
        $this->ScaleHeight($height);
        $this->setParameter('max-h', $this->getHeight());
        return $this;
    }


    /**
     * Crop image on X axis if it exceeds specified width. Retain height.
     * Use in templates with $CropWidth. Example: $Image.ScaleHeight(100).$CropWidth(100)
     *
     * @uses Imgix::Fill()
     * @param integer $width The maximum width of the output image
     * @return Image
     */
    public function CropWidth($width)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::CropWidth($width);
        }
        if ($this->getWidth() > $width) {
            $this->Fill($width, $this->getHeight());
        }
        return $this;
    }

    /**
    * Crop image on Y axis if it exceeds specified height. Retain width.
    * Use in templates with $CropHeight. Example: $Image.ScaleWidth(100).CropHeight(100)
    *
    * @uses Imgix::Fill()
    * @param integer $height The maximum height of the output image
    * @return Image
    */
    public function CropHeight($height)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::CropHeight($height);
        }

        if ($this->getHeight() > $height) {
            $this->Fill($height, $this->getWidth());
        }
        return $this;
    }

    public function Responsive($boolean = true)
    {
        $this->responsive = $boolean;
        return $this;
    }

    public function Compress()
    {
        $this->setParameter('auto', 'compress', true);
        return $this;
    }

    public function Enhance()
    {
        $this->setParameter('auto', 'enhance', true);
        return $this;
    }

    public function Format()
    {
        $this->setParameter('auto', 'format', true);
        return $this;
    }

    public function Redeye()
    {
        $this->setParameter('auto', 'redeye', true);
        return $this;
    }

    public function Top()
    {
        $this->setParameter('crop', 'top', true);
        return $this;
    }

    public function Bottom()
    {
        $this->setParameter('crop', 'bottom', true);
        return $this;
    }

    public function Left()
    {
        $this->setParameter('crop', 'left', true);
        return $this;
    }

    public function Right()
    {
        $this->setParameter('crop', 'right', true);
        return $this;
    }

    public function Faces()
    {
        $this->setParameter('crop', 'faces', true);
        return $this;
    }

    public function Entropy()
    {
        $this->setParameter('crop', 'entropy', true);
        return $this;
    }

    public function Edges()
    {
        $this->setParameter('crop', 'edges', true);
        return $this;
    }

    public function FocusPoint($x = null, $y = null)
    {
        // If focuspoint is installed and coords aren't set then get
        // coords from focuspoint
        if (class_exists('FocusPointImage')) {
            $x = isset($x) ? $x : $this->FocusX;
            $y = isset($y) ? $y : $this->FocusY;
        }

        // Convert coords to decimals if they are percentages.
        $x = $x > 1 ? $x/100 : $x;
        $y = $y > 1 ? $y/100 : $y;

        $this->setParameter('crop','focalpoint');
        $this->setParameter('fp-x', $x);
        $this->setParameter('fp-y', $y);
        return $this;
    }

    public function FocusFill($width, $height)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::FocusFill($width, $height);
        }

        $this->FocusPoint();
        $this->Fill($width, $height);
        return $this;
    }

    public function FocusFillMax($width, $height)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::FocusFillMax($width, $height);
        }
        $this->FocusPoint();
        $this->FillMax($width, $height);
        return $this;
    }

    public function FocusCropWidth($width)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::FocusCropWidth($width);
        }

        $this->FocusPoint();
        $this->CropWidth($width);
        return $this;
    }

    public function FocusCropHeight($height)
    {
        if (Director::isDev() || !$this->config()->get('use_imgix')) {
            return Parent::FocusCropHeight($height);
        }

        $this->FocusPoint();
        $this->CropHeight($height);
        return $this;
    }

    public function setParameter($key, $value, $append = false)
    {
        if (($originalParameters = explode(',', $this->getParameter($key))) && $append) {
            $originalParameters[] = $value;
            $parameters = implode(',', $originalParameters);
        } else {
            $parameters = $value;
        }
        $this->parameters[$key] = $parameters;
        $this->Variant = $this->variantName(__FUNCTION__, $this->parameters);
        return $this;
    }

    public function getParameter($key)
    {
        if (isset($this->parameters[$key])) {
            return $this->parameters[$key];
        }
    }

    /**
     * Determine if this image is of the specified size
     *
     * @param integer $width Width to check
     * @param integer $height Height to check
     * @return boolean
     */
    public function isSize($width, $height)
    {
        return $this->isWidth($width) && $this->isHeight($height);
    }

    /**
    * Determine if this image is of the specified width
    *
    * @param integer $width Width to check
    * @return boolean
    */
    public function isWidth($width)
    {
        return !empty($width) && $this->getWidth() == $width;
    }

    /**
    * Determine if this image is of the specified width
    *
    * @param integer $height Height to check
    * @return boolean
    */
    public function isHeight($height)
    {
        return !empty($height) && $this->getHeight() == $height;
    }

    public function setDimensions($width = null, $height = null)
    {
        if (isset($width)) {
            $this->setParameter('w', $width);
        }
        if (isset($height)) {
            $this->setParameter('h', $height);
        }
        return $this;
    }
}
