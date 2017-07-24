<?php

/**
 * Stupid (Simple) Content Management System
 *
 */
class SCMS
{

    private $contentDir;

    private $cacheDir;

    private $generateCode;
    
    private $articleURLCallback = null;
    
    private $tagURLCallback = null;

    public function __construct(
        /*string*/ $contentDir, 
        /*string*/ $cacheDir, 
        /*string*/ $generateCode, 
        $articleURLCallback = null, 
        $tagURLCallback = null
    )
    {
        $this->contentDir = $contentDir;
        $this->cacheDir = $cacheDir;
        $this->generateCode = $generateCode;
        $this->articleURLCallback = $articleURLCallback;
        $this->tagURLCallback = $tagURLCallback;
    }
    
    public function articleExists(/* string*/ $articleKey)/* : bool*/
    {
        return $articleKey === $this->generateCode || 
        $this->getRepository()->articleExists($articleKey);
    }

    public function getArticle(/* string*/ $articleKey)/* : ISCMSArticle*/
    {
        if ($articleKey === $this->generateCode) {
            $repository = $this->buildRepository();
            $this->buildSitemap($repository);
            
            return new SCMSServiceInfo(
                "Generated repository", 
                "Articles: " . $repository->getArticleCount() . "; " . 
                "Tags: " . $repository->getTagCount()
            );
        }
        
        $repository = $this->getRepository();
        return $repository->getArticle($articleKey);
    }

    public function getAllArticles()/* : array*/
    {
        return $this->getRepository()->getAllArticles();
    }
    
    public function getArticlesForTagKey(/* string*/ $tagKey)/* : array*/
    {
        return $this->getRepository()->getArticlesForTagKey($tagKey);
    }
    
    public function getArticlesForTag(SCMSTag $tag)/* : array*/
    {
        return $this->getArticlesForTagKey($tag->getKey());
    }
    
    public function tagExists(/* string*/ $tagKey)/* : bool*/
    {
        return $this->getRepository()->tagExists($tagKey);
    }
    
    public function getTag(/* string*/ $tagKey)/* : SCMSTag*/
    {
        return $this->getRepository()->getTag($tagKey);
    }
    
    public function getAllTags()/* : array*/
    {
        return $this->getRepository()->getAllTags();
    }

    public function buildRepository()/* : SCMSRepository*/
    {
        $path = $this->getRepositoryPath();
        $repository = new SCMSRepository();
        foreach (glob($this->contentDir . '/*.php') as $filename) {
            $SCMS_TITLE = '';
            $SCMS_DESCRIPTION = '';
            $SCMS_TAGS = array();
            $key = str_replace('.php', '', basename($filename));
            ob_start();
            require ($filename);
            ob_end_clean();
            $tags = array();
            foreach ($SCMS_TAGS as $tag) {
                $tags[] = new SCMSTag(
                    strtolower(preg_replace('~[ ]+~', '-', $tag)),
                    $tag
                );
            }
            $repository->addArticle(
                new SCMSArticle(
                    $key, 
                    $SCMS_TITLE, 
                    $SCMS_DESCRIPTION, 
                    $tags, 
                    $filename
                )
            );
        }
        $repository->updateRelated();
        if (file_put_contents($path, serialize($repository)) === false) {
            throw new LogicException("It's not possible to store repository in $path");
        }
        return $repository;
    }
    
    public function buildSitemap(SCMSRepository $repository) {
        $fh = fopen($this->cacheDir . '/scms-sitemap.xml', 'w');
        fwrite($fh, '<?xml version="1.0" encoding="utf-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" 
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">');
        
        $aC = $this->articleURLCallback;
        foreach ($repository->getAllArticles() as $article) {
            fwrite($fh, '<url><loc>' . $aC($article->getKey()) . '</loc></url>');
        }
        $tC = $this->tagURLCallback;
        foreach ($repository->getAllTags() as $tag) {
            fwrite($fh, '<url><loc>' . $tC($tag->getKey()) . '</loc></url>');
        }
        
        fwrite($fh, '</urlset>');
        fclose($fh);
    }

    private function getRepositoryPath()/* : string*/
    {
        return $this->cacheDir . '/articles.php';
    }

    private function getRepository()/* : SCMSRepository*/
    {
        $path = $this->getRepositoryPath();
        if (! is_file($path)) {
            throw new LogicException("You have to generate content first");
        }
        return unserialize(file_get_contents($path));
    }
}

class SCMSRepository
{

    private $articles = array();

    private $tags = array();

    public function addArticle(SCMSArticle $article)
    {   
        $this->articles[$article->getKey()] = $article;
        
        foreach ($article->getTags() as $tag) {
            if (key_exists($tag->getKey(), $this->tags)) {
                $exTag = $this->tags[$tag->getKey()];
                if ($tag->getTitle() != $exTag->getTitle()) {
                    throw new LogicException(
                        "For article " . $article->getKey() .
                        " there is mismatch in tag title key " .
                        $tag->getKey() . ". Titles are " .
                        $tag->getTitle() . " or " . $exTag->getTitle()
                        );
                    
                }
            } else {
                $exTag = clone $tag;
            }
            $exTag->appendArticleKey($article->getKey());
            $this->tags[$exTag->getKey()] = $exTag;
        }
    }
    
    public function articleExists(/* string*/ $articleKey)/* : bool*/
    {
        return key_exists($articleKey, $this->articles);
    }

    public function getArticle(/* string*/ $articleKey, $refresh = true)/* : SCMSArticle*/
    {
        $article = $this->articles[$articleKey];
        return $refresh 
            ? $this->refreshArticle($article)
            : $article;
    }
    
    public function getAllArticles($refresh = true)/* : array*/ 
    {
        return $refresh
            ? array_map(array($this, 'refreshArticle'), $this->articles)
            : $this->articles;
    }
    
    public function getArticlesForTagKey(/* string*/ $tagKey, $refresh = true)/* : array*/
    {
        $tag = $this->getTag($tagKey);
        $articles = array();
        foreach ($tag->getArticleKeys() as $articleKey) {
            $articles[$articleKey] = $this->getArticle($articleKey);
        }
        return $refresh
            ? array_map(array($this, 'refreshArticle'), $articles)
            : $articles;
    }
    
    public function tagExists(/* string*/ $tagKey)/* : bool*/
    {
        return key_exists($tagKey, $this->tags);
    }
    
    public function getTag(/* string*/ $tagKey)/* : SCMSTag*/
    {
        return $this->tags[$tagKey];
    }
    
    public function getAllTags()/* : array*/
    {
        return $this->tags;
    }

    public function updateRelated()
    {
        
        function inc(&$arr, $k, $l)
        {
            if (! key_exists($k, $arr)) {
                $arr[$k] = array($l => 0);
            }
            if (! key_exists($l, $arr[$k])) {
                $arr[$k][$l] = 0;
            }
            $arr[$k][$l]++;            
        }
        
        $matrix = array();
        foreach ($this->getAllTags() as $tag) {
            $articles = $tag->getArticleKeys();
            $n = count($articles);
            for ($i = 0; $i < $n; ++$i) {
                for ($j = $i + 1; $j < $n; ++$j) {
                    $k = $articles[$i];
                    $l = $articles[$j];
                    inc($matrix, $k, $l);
                    inc($matrix, $l, $k);
                }
            }            
        }
        
        foreach ($matrix as $key => $similar) {
            $related = array();
            asort($similar);
            foreach ($similar as $relKey => $relCount) {
                $related[] = $relKey;
            }
            $this->getArticle($key, false)->setRelatedKeys($related);
        }
    }
    
    public function getArticleCount()/* : int*/ {
        return count($this->articles);
    }
    
    public function getTagCount()/* : int*/ {
        return count($this->tags);
    }
    
    private function refreshArticle(SCMSArticle $article)/* : SCMSArticle*/
    {
        $related = array();                
        foreach ($article->getRelatedKeys() as $relKey) {
            // getArticle cannot be used since it leads in infinite recursion
            $related[] = $this->getArticle($relKey, false);
        }
        $article->setRelated($related);
        return $article;
        
    }
}

interface ISCMSArticle
{

    public function getTitle();

    public function getDescription();

    public function getContent();

    public function getRelated();
    
    public function getTags();
}

class SCMSTag {
    private $key;
    private $title;
    private $articleKeys = array();
    
    public function __construct(
        /*string*/ $key,
        /*string*/ $title
    )
    {
        $this->key = $key;
        $this->title = $title;
    }
    
    public function getTitle()/* : string*/ 
    {
        return $this->title;
    }
    
    public function getKey()/* : string*/ 
    {
        return $this->key;
    }
    
    public function appendArticleKey(/* string*/ $articleKey) 
    {
        $this->articleKeys[] = $articleKey;
    }
    
    public function getArticleKeys()/* : array*/
    {
        return $this->articleKeys;
    }
}

class SCMSArticle implements ISCMSArticle
{

    private $key;

    private $title;

    private $description;

    private $tags;

    private $file;
    
    private $related = array();
    private $relatedKeys = array();

    public function __construct(
        /*string*/ $key, 
        /*string*/ $title, 
        /*string*/ $description, 
        array $tags, 
        /*string*/ $file
    )
    {
        $this->key = $key;
        $this->title = $title;
        $this->description = $description;
        $this->tags = $tags;
        $this->file = $file;
    }

    public function getKey()/* : string*/
    {
        return $this->key;
    }

    public function getTitle()/* : string*/
    {
        return $this->title;
    }

    public function getDescription()/* : string*/
    {
        return $this->description ? $this->description : $this->getTitle();
    }

    public function getContent()/* : string*/
    {
        ob_start();
        include ($this->file);
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
    
    public function getTags()/* : array*/
    {
        return $this->tags;
    }
    
    public function setRelated(array $related)
    {
        $this->related = $related;
    }
    
    public function getRelated()/* : array*/
    {
        return $this->related;
    }
    
    
    public function setRelatedKeys(array $relatedKeys)
    {
        $this->related = array();
        $this->relatedKeys = $relatedKeys;
    }
    
    public function getRelatedKeys()/* : array*/
    {
        return $this->relatedKeys;
    }
    
    
}

class SCMSServiceInfo implements ISCMSArticle
{

    private $title;

    private $content;

    public function __construct(
        /*string*/ $title, 
        /*string*/ $content
    )
    {
        $this->title = $title;
        $this->content = $content;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getDescription()
    {
        return $this->title;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getRelated()
    {
        return null;
    }
    
    public function getTags()
    {
        return null;
    }
}

?>