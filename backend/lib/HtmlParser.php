<?php
/**
 * Wrapper sobre DOMDocument para parsing HTML robusto
 * Suprime errores de parsing y ofrece métodos auxiliares
 */

class HtmlParser {
    private ?DOMDocument $dom = null;
    private string $rawHtml = '';

    /**
     * Carga HTML suprimiendo errores de parsing
     */
    public function loadHtml(string $html): self {
        $this->rawHtml = $html;
        $this->dom = new DOMDocument();

        // Suprimir errores de HTML mal formado
        $prev = libxml_use_internal_errors(true);
        $this->dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $this;
    }

    /**
     * Obtiene contenido de una meta tag por name o property
     */
    public function getMeta(string $name): ?string {
        if ($this->dom === null) return null;

        $metas = $this->dom->getElementsByTagName('meta');
        for ($i = 0; $i < $metas->length; $i++) {
            $meta = $metas->item($i);
            if ($meta === null) continue;

            $metaName = $meta->getAttribute('name');
            $metaProperty = $meta->getAttribute('property');
            $metaHttpEquiv = $meta->getAttribute('http-equiv');

            if (
                strtolower($metaName) === strtolower($name) ||
                strtolower($metaProperty) === strtolower($name) ||
                strtolower($metaHttpEquiv) === strtolower($name)
            ) {
                return $meta->getAttribute('content');
            }
        }
        return null;
    }

    /**
     * Obtiene el contenido de <title>
     */
    public function getTitle(): ?string {
        if ($this->dom === null) return null;

        $titles = $this->dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            $item = $titles->item(0);
            return $item ? trim($item->textContent) : null;
        }
        return null;
    }

    /**
     * Retorna array de todos los headings con su nivel
     */
    public function getHeadings(): array {
        if ($this->dom === null) return [];

        $headings = [];
        for ($level = 1; $level <= 6; $level++) {
            $tags = $this->dom->getElementsByTagName("h$level");
            for ($i = 0; $i < $tags->length; $i++) {
                $item = $tags->item($i);
                if ($item === null) continue;
                $headings[] = [
                    'level' => $level,
                    'tag' => "h$level",
                    'text' => trim($item->textContent),
                ];
            }
        }

        return $headings;
    }

    /**
     * Retorna array de todas las imágenes con src y alt
     */
    public function getImages(): array {
        if ($this->dom === null) return [];

        $images = [];
        $tags = $this->dom->getElementsByTagName('img');
        for ($i = 0; $i < $tags->length; $i++) {
            $img = $tags->item($i);
            if ($img === null) continue;
            $images[] = [
                'src' => $img->getAttribute('src'),
                'alt' => $img->getAttribute('alt'),
                'loading' => $img->getAttribute('loading'),
            ];
        }

        return $images;
    }

    /**
     * Retorna array de todos los enlaces con href y texto
     */
    public function getLinks(): array {
        if ($this->dom === null) return [];

        $links = [];
        $tags = $this->dom->getElementsByTagName('a');
        for ($i = 0; $i < $tags->length; $i++) {
            $link = $tags->item($i);
            if ($link === null) continue;
            $href = $link->getAttribute('href');
            if (!empty($href)) {
                $links[] = [
                    'href' => $href,
                    'text' => trim($link->textContent),
                    'rel' => $link->getAttribute('rel'),
                    'target' => $link->getAttribute('target'),
                ];
            }
        }

        return $links;
    }

    /**
     * Retorna array de todos los scripts con src
     */
    public function getScripts(): array {
        if ($this->dom === null) return [];

        $scripts = [];
        $tags = $this->dom->getElementsByTagName('script');
        for ($i = 0; $i < $tags->length; $i++) {
            $script = $tags->item($i);
            if ($script === null) continue;
            $scripts[] = [
                'src' => $script->getAttribute('src'),
                'type' => $script->getAttribute('type'),
                'inline' => empty($script->getAttribute('src')) ? $script->textContent : '',
            ];
        }

        return $scripts;
    }

    /**
     * Retorna array de todos los links CSS
     */
    public function getStylesheets(): array {
        if ($this->dom === null) return [];

        $styles = [];
        $tags = $this->dom->getElementsByTagName('link');
        for ($i = 0; $i < $tags->length; $i++) {
            $link = $tags->item($i);
            if ($link === null) continue;
            $rel = strtolower($link->getAttribute('rel'));
            if ($rel === 'stylesheet') {
                $styles[] = [
                    'href' => $link->getAttribute('href'),
                ];
            }
        }

        return $styles;
    }

    /**
     * Busca elementos link por rel
     */
    public function getLinkByRel(string $rel): ?string {
        if ($this->dom === null) return null;

        $tags = $this->dom->getElementsByTagName('link');
        for ($i = 0; $i < $tags->length; $i++) {
            $link = $tags->item($i);
            if ($link === null) continue;
            if (strtolower($link->getAttribute('rel')) === strtolower($rel)) {
                return $link->getAttribute('href');
            }
        }
        return null;
    }

    /**
     * Obtiene el atributo lang del tag html
     */
    public function getHtmlLang(): ?string {
        if ($this->dom === null) return null;

        $html = $this->dom->getElementsByTagName('html');
        if ($html->length > 0) {
            $item = $html->item(0);
            $lang = $item ? $item->getAttribute('lang') : '';
            return !empty($lang) ? $lang : null;
        }
        return null;
    }

    /**
     * Obtiene el atributo viewport del meta
     */
    public function getViewport(): ?string {
        return $this->getMeta('viewport');
    }

    /**
     * Busca formularios en el HTML
     */
    public function getForms(): array {
        if ($this->dom === null) return [];

        $forms = [];
        $tags = $this->dom->getElementsByTagName('form');
        for ($i = 0; $i < $tags->length; $i++) {
            $form = $tags->item($i);
            if ($form === null) continue;
            $forms[] = [
                'action' => $form->getAttribute('action'),
                'method' => $form->getAttribute('method'),
                'class' => $form->getAttribute('class'),
                'id' => $form->getAttribute('id'),
            ];
        }

        return $forms;
    }

    /**
     * Busca datos estructurados JSON-LD
     */
    public function getJsonLd(): array {
        if ($this->dom === null) return [];

        $results = [];
        $scripts = $this->dom->getElementsByTagName('script');
        for ($i = 0; $i < $scripts->length; $i++) {
            $script = $scripts->item($i);
            if ($script === null) continue;
            if (strtolower($script->getAttribute('type')) === 'application/ld+json') {
                $data = json_decode(trim($script->textContent), true);
                if ($data !== null) {
                    $results[] = $data;
                }
            }
        }

        return $results;
    }

    /**
     * Obtiene el texto visible del body (sin scripts ni estilos)
     */
    public function getTextContent(): string {
        // Remover scripts y estilos del HTML crudo
        $html = preg_replace('#<script[^>]*>.*?</script>#si', '', $this->rawHtml);
        $html = preg_replace('#<style[^>]*>.*?</style>#si', '', $html);
        $html = preg_replace('#<noscript[^>]*>.*?</noscript>#si', '', $html);

        // Remover tags y decodificar entidades
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Limpiar espacios múltiples
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Cuenta palabras de texto visible
     */
    public function getWordCount(): int {
        $text = $this->getTextContent();
        return str_word_count($text);
    }

    /**
     * Búsqueda por regex en el HTML crudo
     */
    public function findInHtml(string $pattern): array {
        $matches = [];
        preg_match_all($pattern, $this->rawHtml, $matches);
        return $matches[0] ?? [];
    }

    /**
     * Verifica si un patrón existe en el HTML
     */
    public function containsPattern(string $pattern): bool {
        return (bool) preg_match($pattern, $this->rawHtml);
    }

    /**
     * Busca un texto literal en el HTML
     */
    public function contains(string $text): bool {
        return str_contains($this->rawHtml, $text);
    }

    /**
     * Retorna el HTML crudo
     */
    public function getRawHtml(): string {
        return $this->rawHtml;
    }
}
