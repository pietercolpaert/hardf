<?php

declare(strict_types=1);

namespace pietercolpaert\hardf\DataModel;

use BadMethodCallException;
use rdfInterface\LiteralInterface;
use rdfInterface\TermInterface;

/**
 * An RDF Literal.
 *
 * @see https://rdf.js.org/data-model-spec/#literal-interface
 * @see https://github.com/sweetrdf/rdfInterface
 */
final readonly class Literal implements LiteralInterface, Term
{
    public const XSD_STRING = 'http://www.w3.org/2001/XMLSchema#string';
    public const RDF_LANG_STRING = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#langString';
    public const RDF_DIR_LANG_STRING = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#dirLangString';

    /**
     * @param string    $value     the lexical form of the literal
     * @param NamedNode $datatype  the datatype IRI
     * @param string    $language  the BCP47 language tag (empty string when not a language-tagged string)
     * @param string    $direction the base direction: 'ltr', 'rtl', or '' (empty when not directional)
     */
    public function __construct(
        public string $value,
        public NamedNode $datatype,
        public string $language = '',
        public string $direction = '',
    ) {
    }

    public function termType(): string
    {
        return 'Literal';
    }

    /**
     * Returns the lexical form. Convenience alias for {@see getValue()}.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Returns the literal value.
     *
     * Only {@see LiteralInterface::CAST_LEXICAL_FORM} is supported.
     * {@see LiteralInterface::CAST_DATATYPE} will throw a {@see BadMethodCallException}.
     *
     * @throws \BadMethodCallException when $cast is CAST_DATATYPE
     */
    public function getValue(int $cast = LiteralInterface::CAST_LEXICAL_FORM): mixed
    {
        if (LiteralInterface::CAST_DATATYPE === $cast) {
            throw new \BadMethodCallException('CAST_DATATYPE is not supported by '.self::class);
        }

        return $this->value;
    }

    /**
     * Returns the BCP47 language tag, or null when the literal has no language tag.
     *
     * Note: the {@see $language} property uses an empty string for "no language";
     * this method converts that to null as required by rdfInterface.
     */
    public function getLang(): ?string
    {
        return '' !== $this->language ? $this->language : null;
    }

    /**
     * Returns the datatype IRI string.
     *
     * Always returns the full IRI; e.g. 'http://www.w3.org/2001/XMLSchema#string' for a
     * plain literal without a language tag.
     */
    public function getDatatype(): string
    {
        return $this->datatype->value;
    }

    public function withValue(int|float|string|bool|\Stringable $value): static
    {
        $lexical = \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

        return new self($lexical, $this->datatype, $this->language, $this->direction);
    }

    /**
     * Returns a copy with the language tag set to $lang.
     *
     * Setting a language tag changes the datatype to rdf:langString (or rdf:dirLangString when a
     * direction is set). Passing null or '' removes the language tag and sets the datatype to
     * xsd:string.
     */
    public function withLang(?string $lang): static
    {
        if (null === $lang || '' === $lang) {
            return new self($this->value, new NamedNode(self::XSD_STRING), '', '');
        }

        $datatype = '' !== $this->direction
            ? new NamedNode(self::RDF_DIR_LANG_STRING)
            : new NamedNode(self::RDF_LANG_STRING);

        return new self($this->value, $datatype, strtolower($lang), $this->direction);
    }

    /**
     * Returns a copy with the datatype replaced.
     *
     * Setting the datatype always removes the language tag.
     *
     * @throws \BadMethodCallException when $datatype is rdf:langString or empty
     */
    public function withDatatype(string $datatype): static
    {
        if ('' === $datatype || self::RDF_LANG_STRING === $datatype) {
            throw new \BadMethodCallException('Cannot set datatype to rdf:langString or empty string via withDatatype(); use withLang() to set a language tag.');
        }

        return new self($this->value, new NamedNode($datatype), '', '');
    }

    public function equals(TermInterface $term): bool
    {
        return $term instanceof self
            && $term->value === $this->value
            && $term->datatype->equals($this->datatype)
            && $term->language === $this->language
            && $term->direction === $this->direction;
    }

    public function __toString(): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $this->value);
        $base = '"'.$escaped.'"';

        if ('' !== $this->language) {
            $tag = $this->language;
            if ('' !== $this->direction) {
                $tag .= '--'.$this->direction;
            }

            return $base.'@'.$tag;
        }

        if (self::XSD_STRING !== $this->datatype->value) {
            return $base.'^^'.$this->datatype->value;
        }

        return $base;
    }
}
