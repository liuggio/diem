<?php

class dmMediaTagImage extends dmMediaTag
{

  /*
   * available methods and filters for thumbnail creation
   */
  protected static
  $availableMethods = array('fit', 'scale', 'inflate', 'left', 'right', 'top', 'bottom', 'center'),
  $availableFilters = array('greyscale');

  public function initialize()
  {
    parent::initialize();

    $this->method(dmConfig::get('image_resize_method', 'center'));
    $this->set('quality', dmConfig::get('image_quality', 92));
    $this->set('background', null);

    $this->addAttributeToRemove(array('method', 'quality', 'background', 'filter'));
  }

  public function method($method)
  {
    if (!in_array($method, self::getAvailableMethods()))
    {
      throw new dmException(sprintf('%s is not a valid method. These are : %s',
      $method,
      implode(', ', self::getAvailableMethods())
      ));
    }

    return $this->set('method', $method);
  }

  public function quality($v)
  {
    return $this->set('quality', (float) $v);
  }

  public function background($v)
  {
    if (!$hexColor = dmString::hexColor($v))
    {
      throw new dmException(sprintf('%s is not a valid hexadecimal color', $v));
    }
    return $this->set('background', $hexColor);
  }

  public function alt($v)
  {
    return $this->set('alt', (string)$v);
  }

  public function filter($filterName, $filterOptions = array())
  {
    if (!in_array($filterName, self::getAvailableFilters()))
    {
      throw new dmMediaImageException(sprintf('%s is not a valid filter. These are : %s',
      filterName,
      implode(', ', self::$filters)
      ));
    }
    return $this->set('filter', (string)$filterName);
  }

  public function render()
  {
    $tag = '<img'.$this->getHtmlAttributes().' />';

    return $tag;
  }

  protected function prepareAttributesForHtml(array $attributes)
  {
    $attributes = parent::prepareAttributesForHtml($attributes);

    if(!isset($attributes['alt']) && sfConfig::get('dm_accessibility_image_empty_alts', true))
    {
      $attributes['alt'] = '';
    }

    if ($this->resource->isType(dmMediaResource::MEDIA))
    {
      $attributes = $this->prepareMediaAttributes($attributes);
    }

    return $attributes;
  }

  protected function prepareMediaAttributes(array $attributes)
  {
    if ($this->hasSize())
    {
      try
      {
        $mediaFullPath = $this->getResizedMediaFullPath($attributes);
      }
      catch(Exception $e)
      {
        self::$context->getLogger()->err($e->getMessage());
        
        if (sfConfig::get('dm_debug'))
        {
          throw $e;
        }
        
        $mediaFullPath = $this->resource->getSource()->getFullPath();
      }

      $attributes['src'] = $this->requestContext['relative_url_root'].str_replace(sfConfig::get('sf_web_dir'), '', $mediaFullPath);
      /*
       * When thumb method is scale,
       * html width and height
       * should be different than request width and height
       */
      if ($attributes['method'] === 'scale' )
      {
        $infos = getimagesize($mediaFullPath);
        $attributes['width'] = $infos[0];
        $attributes['height'] = $infos[1];
      }
    }

    return $attributes;
  }

  protected function getResizedMediaFullPath(array $attributes)
  {
    $media = $this->resource->getSource();

    if (empty($attributes['width']))
    {
      $attributes['width'] = $media->getWidth();
    }
    elseif (empty($attributes['height']))
    {
      $attributes['height'] = (int) ($media->getHeight() * ($attributes['width'] / $media->getWidth()));
    }

    if ($attributes['method'] == 'fit')
    {
      $attributes['background'] = trim($attributes['background'], '#');
    }

    if (!in_array($attributes['method'], self::getAvailableMethods()))
    {
      throw new dmException(sprintf('%s is not a valid resize method. These are : %s', $attributes['method'], implode(', ', self::getAvailableMethods())));
    }

    if(!self::$context->getFilesystem()->mkdir($thumbDir = dmOs::join($media->get('Folder')->getFullPath(), '.thumbs')))
    {
      throw new dmException('Thumbnails can not be created in '.$media->get('Folder')->getFullPath());
    }

    $filter = dmArray::get($attributes, 'filter');

    $thumbBasename = sprintf('%sx%s-%s_%s_%s_%d_%s',
    $attributes['width'],
    $attributes['height'],
    $attributes['method'] == 'fit' ? 'fit'.$attributes['background'] : $attributes['method'],
    $filter,
    $attributes['quality'],
    $media->getLittleMTime(),
    $media->file
    );

    $thumbPath = dmOs::join($thumbDir, $thumbBasename);

    if (!file_exists($thumbPath))
    {
      self::$context->getLogger()->notice('dmMediaTagImage : create thumb for media '.$media);

      $image = $media->getImage();

      $image->setQuality($attributes['quality']);

      $image->thumbnail($attributes['width'], $attributes['height'], $attributes['method'], isset($attributes['background']) ? '#'.$attributes['background'] : null);

      if ($filter)
      {
        $image->$filter();
      }

      $image->saveAs($thumbPath, $media->get('mime'));

      if (!file_exists($thumbPath))
      {
        throw new dmException(dmProject::unRootify($thumbPath).' cannot be created');
      }
    }

    return $thumbPath;
  }

  public static function getAvailableFilters()
  {
    return self::$availableFilters;
  }

  public static function getAvailableMethods()
  {
    return self::$availableMethods;
  }

}