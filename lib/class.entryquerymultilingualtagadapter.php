<?php

/**
 * @package toolkit
 */
/**
 * Specialized EntryQueryFieldAdapter that facilitate creation of queries filtering/sorting data from
 * an Multilingual Tag Field.
 * @see FieldMultilingualTag
 * @since Symphony 3.0.0
 */
class EntryQueryMultilingualTagAdapter extends EntryQueryListAdapter
{
    public function getFilterColumns()
    {
        $lc = FLang::getLangCode();

        if ($lc) {
            return ["value-$lc", "handle-$lc"];
        }

        return parent::getFilterColumns();
    }

    public function getSortColumns()
    {
        $lc = FLang::getLangCode();

        if ($lc) {
            return ["value-$lc"];
        }

        return parent::getSortColumns();
    }
}
