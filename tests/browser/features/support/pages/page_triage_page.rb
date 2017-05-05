class PageTriagePage
  include PageObject

  page_url 'Special:NewPagesFeed'

  radio_button(:blocked, id: 'mwe-pt-filter-blocked')
  radio_button(:bots, id: 'mwe-pt-filter-bot-edits')
  checkbox(:deletion, id: 'mwe-pt-filter-nominated-for-deletion')
  a(:learn_more, href: %r{Wikipedia:Page_Curation/Help}, text: 'Learn more')
  a(:leave_feedback, href: /Wikipedia_talk:Page_Curation/, text: 'Leave feedback')
  select(:namespace, id: 'mwe-pt-filter-namespace')
  radio_button(:new_editors, id: 'mwe-pt-filter-non-autoconfirmed')
  radio_button(:no_categories, id: 'mwe-pt-filter-no-categories')
  radio_button(:orphan, id: 'mwe-pt-filter-orphan')
  checkbox(:redirects, id: 'mwe-pt-filter-redirects')
  a(:review, text: 'Review')
  checkbox(:reviewed_pages, id: 'mwe-pt-filter-reviewed-edits')
  span(:set_filters, id: 'mwe-pt-filter-dropdown-control')
  div(:status, class: 'mwe-pt-status-icon')
  checkbox(:unreviewed_pages, id: 'mwe-pt-filter-unreviewed-edits')
  radio_button(:user_selected, id: 'mwe-pt-filter-user-selected')
  text_field(:username, id: 'mwe-pt-filter-user')
end
