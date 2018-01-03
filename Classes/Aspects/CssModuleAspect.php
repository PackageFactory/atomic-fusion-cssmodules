<?php
namespace PackageFactory\AtomicFusion\CssModules\Aspects;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Reflection\ClassReflection;
use Neos\Fusion\FusionObjects\AbstractFusionObject;
use Neos\Neos\Service\HtmlAugmenter;

/**
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class CssModuleAspect
{
    /**
     * @Flow\Inject
     * @var HtmlAugmenter
     */
    protected $htmlAugmenter;

    /**
     * @Flow\InjectConfiguration(path="tryFiles")
     * @var array
     */
    protected $tryFiles;

    /**
     * @Flow\InjectConfiguration(path="contextName")
     * @var array
     */
    protected $contextName;

    /**
     * @Flow\Around("setting(PackageFactory.AtomicFusion.CssModules.enable) && method(PackageFactory\AtomicFusion\FusionObjects\ComponentImplementation->evaluate())")
     * @Flow\Around("setting(PackageFactory.AtomicFusion.CssModules.enable) && method(Neos\Fusion\FusionObjects\ComponentImplementation->evaluate())")
     * @param JoinPointInterface $joinPoint
     * @return mixed
     */
    public function addStyleInformationToComponent(JoinPointInterface $joinPoint)
    {
        $componentImplementation = $joinPoint->getProxy();
        $fusionPrototypeName = $this->getFusionObjectNameFromFusionObject($componentImplementation);

        if ($cssModuleFileName = $this->getCssModuleFileNameFromFusionPrototypeName($fusionPrototypeName)) {
            $cssModuleContents = file_get_contents($cssModuleFileName);
            $styles = json_decode($cssModuleContents, true);

            $context = $componentImplementation->getRuntime()->getCurrentContext();
            $context[$this->contextName] = $styles;
            $componentImplementation->getRuntime()->pushContextArray($context);
            $renderedComponent = $joinPoint->getAdviceChain()->proceed($joinPoint);
            $componentImplementation->getRuntime()->popContext();

            return $renderedComponent;
        }

        return $joinPoint->getAdviceChain()->proceed($joinPoint);
    }

    public function getCssModuleFileNameFromFusionPrototypeName(string $fusionPrototypeName) : string
    {
        list($packageKey, $componentName) = explode(':', $fusionPrototypeName);
        $fusionPrototypeNameSegments = explode('.', $componentName);
        $componentPath = implode('/', $fusionPrototypeNameSegments);
        $componentBaseName = array_pop($fusionPrototypeNameSegments);

        foreach ($this->tryFiles as $fileNamePattern) {
            $fileName = $fileNamePattern;
            $fileName = str_replace('{fusionPrototypeName}', $fusionPrototypeName, $fileName);
            $fileName = str_replace('{packageKey}', $packageKey, $fileName);
            $fileName = str_replace('{componentPath}', $componentPath, $fileName);
            $fileName = str_replace('{componentBaseName}', $componentBaseName, $fileName);

            if (file_exists($fileName)) {
                return $fileName;
            }
        }

        return '';
    }

    public function getFusionObjectNameFromFusionObject(AbstractFusionObject $fusionObject) : string
    {
        $fusionObjectReflection = new ClassReflection($fusionObject);
            $fusionObjectName = $fusionObjectReflection->getProperty('fusionObjectName')->getValue($fusionObject);

        return $fusionObjectName;
    }
}
