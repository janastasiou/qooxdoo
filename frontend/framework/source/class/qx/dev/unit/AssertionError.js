/* ************************************************************************

   qooxdoo - the new era of web development

   http://qooxdoo.org

   Copyright:
     2007-2008 1&1 Internet AG, Germany, http://www.1und1.de

   License:
     LGPL: http://www.gnu.org/licenses/lgpl.html
     EPL: http://www.eclipse.org/org/documents/epl-v10.php
     See the LICENSE file in the project's top-level directory for details.

   Authors:
     * Fabian Jakobs (fjakobs)

************************************************************************ */

qx.Class.define("qx.dev.unit.AssertionError",
{
  extend : Error,




  /*
  *****************************************************************************
     CONSTRUCTOR
  *****************************************************************************
  */

  construct : function(comment, failMessage)
  {
    Error.call(this, failMessage);
    this.setComment(comment || "");
    this.setMessage(failMessage || "");

    this._trace = qx.dev.StackTrace.getStackTrace();
  },





  /*
  *****************************************************************************
     PROPERTIES
  *****************************************************************************
  */

  properties :
  {
    comment :
    {
      check : "String",
      init  : ""
    },

    message :
    {
      check : "String",
      init  : ""
    }
  },




  /*
  *****************************************************************************
     MEMBERS
  *****************************************************************************
  */

  members :
  {
    /**
     * TODOC
     *
     * @type member
     * @return {var} TODOC
     */
    toString : function() {
      return this.getComment() + ": " + this.getMessage();
    },


    /**
     * TODOC
     *
     * @type member
     * @return {var} TODOC
     */
    getStackTrace : function() {
      return this._trace;
    }
  }
});
