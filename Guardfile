guard 'less', :all_on_start => true, :all_after_change => true do
  watch(%r{resources/css/patrol.less$})
end

guard 'livereload' do
  watch(%r{resources/.+\.(css|js)})
  watch(%r{templates/.+\.(html|twig)})
  watch(%r{.+\.(php)})
end
