<?php
/**
 * @package Newscoop
 * @copyright 2011 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */
class Admin_Form_Theme_OutputSettings extends Zend_Form
{
    public function init()
    {   
        $translator = \Zend_Registry::get('container')->getService('translator');
        
        $this
            ->setAttrib( "autocomplete", "off" )
            ->setAction( '' )
            ->setMethod( Zend_Form::METHOD_POST )
            ->addElement( 'select', 'frontpage', array
            (
                'label'       => $translator->trans( 'Front page template' , array(), 'themes'),
            	'required'    => true,
            ))
            ->addElement( 'select', 'sectionpage', array
            (
    			'label'       => $translator->trans( 'Section page template', array(), 'themes' ),
            	'required'    => true,
            ))
            ->addElement( 'select', 'articlepage', array
            (
    			'label'       => $translator->trans( 'Article page template', array(), 'themes' ),
            	'required'    => true,
            ))
            ->addElement( 'select', 'errorpage', array
            (
    			'label'       => $translator->trans( 'Error page template', array(), 'themes' ),
            	'required'    => true,
            ))
            ->addElement( 'hidden', 'outputid', array
            (
            	'required'    => true,
            ))
            ->addElement( 'hidden', 'themeid', array
            (
            	'required'    => true,
            ))
            ->addElement( 'submit', 'submit', array
            (
                'label'		=> 'Save'
            ) );

        // take out those decorators for the hidden elements
        foreach( array( $this->getElement( 'outputid' ), $this->getElement( 'themeid' ) ) as $elem ) {
            $elem
                ->removeDecorator( 'HtmlTag' )
                ->removeDecorator( 'Label' )
                ->removeDecorator( 'Error' )
                ->removeDecorator( 'Description' );
        }
    }

    /**
     * Set values for the form elements
     * @param array $defaults Default template files
     * @param array $values Default input values
     */
    public function setValues( $defaults, $values = null )
    {
        foreach( $this->getElements() as $elem ) {
        	/* @var $elem Zend_Form_Element_Select */
            if( $elem instanceof Zend_Form_Element_Select )
            {
                $elem->addMultiOptions( $defaults )->setValue( $values[ $elem->getName() ] );
            }
            elseif(isset($values[ $elem->getName() ]))
            {
                $elem->setValue( $values[ $elem->getName() ] );
            }
        }
    }
}