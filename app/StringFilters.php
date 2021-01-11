<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 */

namespace GSC;

/**
 * String filters interface
 */
interface IStringFilters
{
    public static function convert_eol_to_br(&$content);
    public static function convert_eolhyphen_to_brdot(&$content);
    public static function correct_text_spacing(&$content, $language);
    public static function trim_eol(&$content);
    public static function trim_html_comment(&$content);
}

/**
 * String filters - modify content passed by a reference
 */
class StringFilters implements IStringFilters
{

    /**
     * Convert EOLs to <br>
     *
     * @param string $content (by reference)
     * @return void
     */
    public static function convert_eol_to_br(&$content)
    {
        if (!is_string($content)) {
            return;
        }

        $content = str_replace(array(
            "\n",
            "\r\n",
        ), "<br>", (string) $content);
    }

    /**
     * Convert EOL + hyphen/star to HTML
     *
     * @param string $content (by reference)
     * @return void
     */
    public static function convert_eolhyphen_to_brdot(&$content)
    {
        if (!is_string($content)) {
            return;
        }

        $content = str_replace(array(
            "\n* ",
            "\n- ",
            "\r\n* ",
            "\r\n- ",
        ), "<br>•&nbsp;", (string) $content);

        // fix for the beginning of the string
        if ((substr($content, 0, 2) == "- ") || (substr($content, 0, 2) == "* ")) {
            $content = "•&nbsp;" . substr($content, 2);
        }
    }

    /**
     * Trim various EOL combinations
     *
     * @param string $content (by reference)
     * @return void
     */
    public static function trim_eol(&$content)
    {
        if (!is_string($content)) {
            return;
        }

        $content = str_replace(array(
            "\r\n",
            "\n",
            "\r",
        ), "", (string) $content);
    }

    /**
     * Trim THML comments
     *
     * @param string $content (by reference)
     * @return void
     */
    public static function trim_html_comment(&$content)
    {
        if (!is_string($content)) {
            return;
        }

        $body = "<body";
        $c = explode($body, (string) $content, 2);
        $regex = '/<!--(.|\s)*?-->/';

        // fix only comments inside body
        if (count($c) == 2) {
            $c[1] = preg_replace($regex, "<!-- comment removed -->", $c[1]);
            $content = $c[0] . $body . $c[1];
        }

        // fix the whole string (there is no <body)
        if (count($c) == 1) {
            $content = preg_replace($regex, "<!-- comment removed -->", $content);
        }
    }

    /**
     * Correct text spacing
     *
     * @param string $content (by reference)
     * @param string $language (optional: "cs", "en" - for now)
     * @return void
     */
    public static function correct_text_spacing(&$content, $language = "en")
    {
        if (!is_string($content)) {
            return;
        }

        $language = strtolower(trim((string) $language));
        switch ($language) {
            case "sk":
            case "cs":
                $content = self::_correct_text_spacing_cs($content);
                break;

            default:
                $content = self::_correct_text_spacing_en($content);
        }
    }

    /**
     * Correct text spacing for English language
     *
     * @param string $content textual data
     * @return string
     */
    public static function _correct_text_spacing_en($content)
    {
        if (!is_string($content)) {
            return;
        }

        $replace = array(
            "  " => " ",
            " % " => "&nbsp;% ",
            " - " => " – ",
            " ... " => "&nbsp;… ",
            " ..." => "&nbsp;…",
            " :-(" => "&nbsp;😟",
            " :-)" => "&nbsp;🙂",
            " :-O" => "&nbsp;😮",
            " :-P" => "&nbsp;😋",
            " :-[" => "&nbsp;😕",
            " :-|" => "&nbsp;😐",
            " A " => " A&nbsp;",
            " AM" => "&nbsp;AM",
            " CZK " => " CZK&nbsp;",
            " Czk " => " CZK&nbsp;",
            " EUR " => " EUR&nbsp;",
            " Eur " => " EUR&nbsp;",
            " I " => " I&nbsp;",
            " ID: " => " ID:&nbsp;",
            " Inc." => "&nbsp;Inc.",
            " Ltd." => "&nbsp;Ltd.",
            " Miss " => " Miss&nbsp;",
            " Mr " => " Mr&nbsp;",
            " Mr. " => " Mr.&nbsp;",
            " Ms " => " Ms&nbsp;",
            " Ms. " => " Ms.&nbsp;",
            " PM" => "&nbsp;PM",
            " USD " => " USD&nbsp;",
            " Usd " => " USD&nbsp;",
            " a " => " a&nbsp;",
            " h " => "&nbsp;h ",
            " h" => "&nbsp;h",
            " h, " => "&nbsp;h, ",
            " h. " => "&nbsp;h. ",
            " id: " => " id:&nbsp;",
            " kg " => "&nbsp;kg ",
            " l " => "&nbsp;l ",
            " l, " => "&nbsp;l, ",
            " l. " => "&nbsp;l. ",
            " m " => "&nbsp;m ",
            " m, " => "&nbsp;m, ",
            " m. " => "&nbsp;m. ",
            " m2 " => "&nbsp;m² ",
            " m3 " => "&nbsp;m³ ",
            " m² " => "&nbsp;m² ",
            " m³ " => "&nbsp;m³ ",
            " pcs" => "&nbsp;pcs",
            " pcs)" => "&nbsp;pcs)",
            " s " => "&nbsp;s ",
            " s, " => "&nbsp;s, ",
            " s. " => "&nbsp;s. ",
            " sec. " => "&nbsp;sec. ",
            " z. s." => "&nbsp;z.&nbsp;s.",
            " °C " => "&nbsp;°C ",
            " °F " => "&nbsp;°F ",
            " ‰ " => "&nbsp;‰",
            "<<" => "«",
            ">>" => "»",
        );
        return str_replace(array_keys($replace), $replace, $content);
    }

    /**
     * Correct text spacing for Czech language
     *
     * @param string $content textual data
     * @return string
     */
    public static function _correct_text_spacing_cs($content)
    {
        if (!is_string($content)) {
            return;
        }

        $replace = array(
            "  " => " ",
            " % " => "&nbsp;%",
            " - " => " – ",
            " ... " => "&nbsp;… ",
            " ..." => "&nbsp;…",
            " :-(" => "&nbsp;😟",
            " :-)" => "&nbsp;🙂",
            " :-O" => "&nbsp;😮",
            " :-P" => "&nbsp;😋",
            " :-[" => "&nbsp;😕",
            " :-|" => "&nbsp;😐",
            " CZK" => "&nbsp;CZK",
            " Czk" => "&nbsp;CZK",
            " DIČ: " => " DIČ:&nbsp;",
            " EUR" => "&nbsp;EUR",
            " Eur " => "&nbsp;EUR ",
            " ID: " => " ID:&nbsp;",
            " Inc." => "&nbsp;Inc.",
            " IČ: " => " IČ:&nbsp;",
            " Kč" => "&nbsp;Kč",
            " Ltd." => "&nbsp;Ltd.",
            " USD" => "&nbsp;USD",
            " Usd" => "&nbsp;USD",
            " a " => " a&nbsp;",
            " cca. " => " cca.&nbsp;",
            " h" => "&nbsp;h",
            " h " => "&nbsp;h&nbsp;",
            " h, " => "&nbsp;h, ",
            " h. " => "&nbsp;h. ",
            " hod. " => "&nbsp;hod. ",
            " hod.)" => "&nbsp;hod.)",
            " i " => " i&nbsp;",
            " id: " => " id:&nbsp;",
            " k " => " k&nbsp;",
            " kg " => "&nbsp;kg ",
            " kg)" => "&nbsp;kg)",
            " ks " => "&nbsp;ks ",
            " ks)" => "&nbsp;ks)",
            " ks, " => "&nbsp;ks, ",
            " ks." => "&nbsp;ks.",
            " kupř. " => " kupř.&nbsp;",
            " l " => "&nbsp;l ",
            " l, " => "&nbsp;l, ",
            " l. " => "&nbsp;l. ",
            " m " => "&nbsp;m ",
            " m, " => "&nbsp;m, ",
            " m. " => "&nbsp;m. ",
            " m2 " => "&nbsp;m² ",
            " m3 " => "&nbsp;m³ ",
            " mj. " => " mj.&nbsp;",
            " m² " => "&nbsp;m² ",
            " m³ " => "&nbsp;m³ ",
            " např. " => " např.&nbsp;",
            " o " => " o&nbsp;",
            " p. " => " p.&nbsp;",
            " popř. " => " popř.&nbsp;",
            " př. " => " př.&nbsp;",
            " přib. " => " přib.&nbsp;",
            " přibl. " => " přibl.&nbsp;",
            " s " => " s&nbsp;",
            " s, " => "&nbsp;s, ",
            " s. " => "&nbsp;s. ",
            " s.r.o." => "&nbsp;s.r.o.",
            " sec. " => "&nbsp;sec. ",
            " sl. " => " sl.&nbsp;",
            " spol. " => "&nbsp;spol.&nbsp;",
            " str. " => " str.&nbsp;",
            " sv. " => " sv.&nbsp;",
            " tj. " => "tj.&nbsp;",
            " tzn. " => " tzn.&nbsp;",
            " tzv. " => " tzv.&nbsp;",
            " tř. " => "tř.&nbsp;",
            " u " => " u&nbsp;",
            " v " => " v&nbsp;",
            " viz " => " viz&nbsp;",
            " z " => " z&nbsp;",
            " z. s." => "&nbsp;z.&nbsp;s.",
            " zvl. " => " zvl.&nbsp;",
            " °C " => "&nbsp;°C ",
            " °F " => "&nbsp;°F ",
            " č. " => " č.&nbsp;",
            " č. j. " => " č.&nbsp;j.&nbsp;",
            " čj. " => " čj.&nbsp;",
            " čp. " => " čp.&nbsp;",
            " čís. " => " čís.&nbsp;",
            " ‰ " => "&nbsp;‰",
            "<<" => "«",
            ">>" => "»",
        );
        return str_replace(array_keys($replace), $replace, $content);
    }
}
