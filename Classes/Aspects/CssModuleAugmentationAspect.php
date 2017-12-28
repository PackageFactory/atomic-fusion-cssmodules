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
class CssModuleAugmentationAspect
{
    /**
     * @Flow\Inject
     * @var HtmlAugmenter
     */
    protected $htmlAugmenter;

    /**
     * @Flow\Around("setting(PackageFactory.AtomicFusion.CssModules.enable) && method(PackageFactory\AtomicFusion\FusionObjects\ComponentImplementation->evaluate())")
     * @Flow\Around("setting(PackageFactory.AtomicFusion.CssModules.enable) && method(Neos\Fusion\FusionObjects\ComponentImplementation->evaluate())")
     * @param JoinPointInterface $joinPoint
     * @return mixed
     */
    public function addStyleInformationToComponent(JoinPointInterface $joinPoint)
    {
        $componentImplementation = $joinPoint->getProxy();
        $fusionObjectName = $this->getFusionObjectNameFromFusionObject($componentImplementation);
        $packageName = $this->getPackageNameFromFusionObject($componentImplementation);

        list($packageName, $componentName) = explode(':', $fusionObjectName);
        $componentNameSegements = explode('.', $componentName);
        $componentPath = implode('/', $componentNameSegements);
        $trailingComponentNameSegment = array_pop($componentNameSegements);

        $jsonFileCandidates = [
            sprintf('resource://%s/Private/Fusion/%s/%s.css.json', $packageName, $componentPath, $trailingComponentNameSegment),
            sprintf('resource://%s/Private/Fusion/%s/style.css.json', $packageName, $componentPath),
            sprintf('resource://%s/Private/Fusion/%s/Style.css.json', $packageName, $componentPath),
            sprintf('resource://%s/Private/Fusion/%s/styles.css.json', $packageName, $componentPath),
            sprintf('resource://%s/Private/Fusion/%s/Styles.css.json', $packageName, $componentPath),
            sprintf('resource://%s/Private/Fusion/%s/index.css.json', $packageName, $componentPath),
            sprintf('resource://%s/Private/Fusion/%s/Index.css.json', $packageName, $componentPath),
            sprintf('resource://%s/Private/Fusion/%s/component.css.json', $packageName, $componentPath),
            sprintf('resource://%s/Private/Fusion/%s/Component.css.json', $packageName, $componentPath),
            sprintf('resource://%s/Private/Fusion/%s.css.json', $packageName, $componentPath)
        ];

        $jsonFileName = null;
        foreach ($jsonFileCandidates as $jsonFileCandidate) {
            if (file_exists($jsonFileCandidate)) {
                $jsonFileName = $jsonFileCandidate;
                break;
            }
        }

        if (!$jsonFileName) {
            return $joinPoint->getAdviceChain()->proceed($joinPoint);
        }

        $jsonFileContents = file_get_contents($jsonFileName);
        $styles = json_decode($jsonFileContents, true);

        $context = $componentImplementation->getRuntime()->getCurrentContext();
        $context['styles'] = $styles;
        $componentImplementation->getRuntime()->pushContextArray($context);
        $renderedComponent = $joinPoint->getAdviceChain()->proceed($joinPoint);
        $componentImplementation->getRuntime()->popContext();

        return $renderedComponent;
    }

    public function getFusionObjectNameFromFusionObject(AbstractFusionObject $fusionObject)
    {
        $fusionObjectReflection = new ClassReflection($fusionObject);
            $fusionObjectName = $fusionObjectReflection->getProperty('fusionObjectName')->getValue($fusionObject);

        return $fusionObjectName;
    }

    /**
     * Get the package name for a given fusion object
     *
     * @param AbstractFusionObject $fusionObject
     * @return string
     */
    public function getPackageNameFromFusionObject(AbstractFusionObject $fusionObject)
    {
        $fusionObjectName = $this->getFusionObjectNameFromFusionObject($fusionObject);

        list($packageName) = explode(':', $fusionObjectName);

        return $packageName;
    }
}
