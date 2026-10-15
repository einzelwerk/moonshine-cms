<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

use MoonShine\Support\Enums\HttpMethod;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Fields\Hidden;

/**
 * The buttons of a card head that write, built as ordinary MoonShine action
 * buttons: the confirmation is a modal of the panel and not a dialog of the
 * browser, and the request is a POST with the token.
 *
 * The button lives inside the list and not inside a form, and its modal is
 * teleported to the body, so the form of the card stays untouched.
 */
final readonly class EditorActions
{
    /**
     * Removing one block. The identifier travels as a field of the
     * confirmation form: the address of the command names the owner only.
     */
    public static function delete(BlockCard $card, string $url, string $event): string
    {
        return ActionButton::make('', $url)
            ->name('cms-delete-'.$card->id)
            ->icon('trash')
            ->error()
            ->class('btn-square')
            ->customAttributes([
                'data-cms-delete' => (string) $card->id,
                'title' => (string) __('moonshine-cms::editor.delete'),
            ])
            ->async(HttpMethod::POST, events: [$event])
            ->withConfirm(
                title: (string) __('moonshine-cms::editor.confirm_delete_title'),
                content: (string) __('moonshine-cms::editor.confirm_delete'),
                button: (string) __('moonshine-cms::editor.delete'),
                fields: [Hidden::make('block')->setValue($card->id)],
                name: 'cms-delete-modal-'.$card->id,
            )
            ->__toString();
    }

    /**
     * Publishing the owner. It rewrites the snapshot the site serves, so it
     * asks first; a refusal comes back as the list of what stands in the way
     * and nothing is written.
     */
    public static function publish(string $url, string $event): string
    {
        return ActionButton::make((string) __('moonshine-cms::editor.publish'), $url)
            ->name('cms-publish')
            ->icon('rocket-launch')
            ->primary()
            ->customAttributes(['data-cms-publish' => 'true'])
            ->async(HttpMethod::POST, events: [$event])
            ->withConfirm(
                title: (string) __('moonshine-cms::editor.confirm_publish_title'),
                content: (string) __('moonshine-cms::editor.confirm_publish'),
                button: (string) __('moonshine-cms::editor.publish'),
                name: 'cms-publish-modal',
            )
            ->__toString();
    }

    /**
     * Any other command that writes and asks first. The confirmation modal is
     * teleported to the body, so the button is placed on a form page as well
     * without a form ending up inside another.
     */
    public static function confirmed(
        string $label,
        string $url,
        string $confirm,
        string $name,
        string $icon,
        string $marker,
    ): string {
        return ActionButton::make($label, $url)
            ->name($name)
            ->icon($icon)
            ->customAttributes([$marker => 'true'])
            ->async(HttpMethod::POST)
            ->withConfirm(content: $confirm, name: $name.'-modal')
            ->__toString();
    }
}
