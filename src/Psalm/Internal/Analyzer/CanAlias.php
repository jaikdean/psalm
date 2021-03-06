<?php
namespace Psalm\Internal\Analyzer;

use PhpParser;
use Psalm\Aliases;
use Psalm\CodeLocation;
use Psalm\Internal\FileManipulation\FileManipulationBuffer;

trait CanAlias
{
    /**
     * @var array<string, string>
     */
    private $aliased_classes = [];

    /**
     * @var array<string, CodeLocation>
     */
    private $aliased_class_locations = [];

    /**
     * @var array<string, string>
     */
    private $aliased_classes_flipped = [];

    /**
     * @var array<string, string>
     */
    private $aliased_classes_flipped_replaceable = [];

    /**
     * @var array<string, string>
     */
    private $aliased_functions = [];

    /**
     * @var array<string, string>
     */
    private $aliased_constants = [];

    /**
     * @param  PhpParser\Node\Stmt\Use_ $stmt
     *
     * @return void
     */
    public function visitUse(PhpParser\Node\Stmt\Use_ $stmt)
    {
        $codebase = $this->getCodebase();

        foreach ($stmt->uses as $use) {
            $use_path = implode('\\', $use->name->parts);
            $use_alias = $use->alias ? $use->alias->name : $use->name->getLast();

            switch ($use->type !== PhpParser\Node\Stmt\Use_::TYPE_UNKNOWN ? $use->type : $stmt->type) {
                case PhpParser\Node\Stmt\Use_::TYPE_FUNCTION:
                    $this->aliased_functions[strtolower($use_alias)] = $use_path;
                    break;

                case PhpParser\Node\Stmt\Use_::TYPE_CONSTANT:
                    $this->aliased_constants[$use_alias] = $use_path;
                    break;

                case PhpParser\Node\Stmt\Use_::TYPE_NORMAL:
                    if ($codebase->collect_locations) {
                        // register the path
                        $codebase->use_referencing_locations[strtolower($use_path)][] =
                            new \Psalm\CodeLocation($this, $use);

                        $codebase->use_referencing_files[$this->getFilePath()][strtolower($use_path)] = true;
                    }

                    if ($codebase->alter_code) {
                        if (isset($codebase->class_transforms[strtolower($use_path)])) {
                            $new_fq_class_name = $codebase->class_transforms[strtolower($use_path)];

                            $file_manipulations = [];

                            $file_manipulations[] = new \Psalm\FileManipulation(
                                (int) $use->getAttribute('startFilePos'),
                                (int) $use->getAttribute('endFilePos') + 1,
                                $new_fq_class_name
                            );

                            FileManipulationBuffer::add($this->getFilePath(), $file_manipulations);
                        }

                        $this->aliased_classes_flipped_replaceable[strtolower($use_path)] = $use_alias;
                    }

                    $this->aliased_classes[strtolower($use_alias)] = $use_path;
                    $this->aliased_class_locations[strtolower($use_alias)] = new CodeLocation($this, $stmt);
                    $this->aliased_classes_flipped[strtolower($use_path)] = $use_alias;
                    break;
            }
        }
    }

    /**
     * @param  PhpParser\Node\Stmt\GroupUse $stmt
     *
     * @return void
     */
    public function visitGroupUse(PhpParser\Node\Stmt\GroupUse $stmt)
    {
        $use_prefix = implode('\\', $stmt->prefix->parts);

        $codebase = $this->getCodebase();

        foreach ($stmt->uses as $use) {
            $use_path = $use_prefix . '\\' . implode('\\', $use->name->parts);
            $use_alias = $use->alias ? $use->alias->name : $use->name->getLast();

            switch ($use->type !== PhpParser\Node\Stmt\Use_::TYPE_UNKNOWN ? $use->type : $stmt->type) {
                case PhpParser\Node\Stmt\Use_::TYPE_FUNCTION:
                    $this->aliased_functions[strtolower($use_alias)] = $use_path;
                    break;

                case PhpParser\Node\Stmt\Use_::TYPE_CONSTANT:
                    $this->aliased_constants[$use_alias] = $use_path;
                    break;

                case PhpParser\Node\Stmt\Use_::TYPE_NORMAL:
                    if ($codebase->collect_locations) {
                        // register the path
                        $codebase->use_referencing_locations[strtolower($use_path)][] =
                            new \Psalm\CodeLocation($this, $use);
                    }

                    $this->aliased_classes[strtolower($use_alias)] = $use_path;
                    $this->aliased_classes_flipped[strtolower($use_path)] = $use_alias;
                    break;
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function getAliasedClassesFlipped()
    {
        return $this->aliased_classes_flipped;
    }

    /**
     * @return array<string, string>
     */
    public function getAliasedClassesFlippedReplaceable()
    {
        return $this->aliased_classes_flipped_replaceable;
    }

    /**
     * @return Aliases
     */
    public function getAliases()
    {
        return new Aliases(
            $this->getNamespace(),
            $this->aliased_classes,
            $this->aliased_functions,
            $this->aliased_constants
        );
    }
}
